<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\StockAdjustment;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StockAdjustmentRepository extends BaseRepository implements BranchScopedInterface, StockAdjustmentRepositoryInterface
{
    protected function model(): string
    {
        return StockAdjustment::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['reference_no', 'note'])),
            'branch_id',
            'reason',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'occurred_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['items', 'items.product', 'branch', 'adjustedBy'];
    }

    protected function defaultSort(): string
    {
        return '-occurred_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['items.product', 'adjustedBy']);
    }

    public function createAdjustment(array $values): StockAdjustment
    {
        return StockAdjustment::create($values);
    }

    public function addItem(StockAdjustment $adjustment, array $values): void
    {
        $adjustment->items()->create($values);
    }
}
