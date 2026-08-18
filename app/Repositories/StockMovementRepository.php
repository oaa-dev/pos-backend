<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\StockMovement;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockMovementRepository extends BaseRepository implements BranchScopedInterface, StockMovementRepositoryInterface
{
    protected function model(): string
    {
        return StockMovement::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['note', 'product.name', 'product.sku'])),
            'branch_id',
            'product_id',
            'type',
            AllowedFilter::callback(
                'occurred_from',
                fn ($query, $value) => $query->whereDate('occurred_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'occurred_to',
                fn ($query, $value) => $query->whereDate('occurred_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'occurred_at', 'quantity_base'];
    }

    protected function allowedIncludes(): array
    {
        return ['product', 'productUnit', 'batch', 'user', 'branch'];
    }

    protected function defaultSort(): string
    {
        return '-occurred_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['product', 'productUnit.unit', 'user']);
    }

    public function record(array $values): StockMovement
    {
        return StockMovement::create($values);
    }

    public function sumBaseQuantity(int $branchId, int $productId): string
    {
        return (string) ($this->builder()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('quantity_base') ?: '0');
    }
}
