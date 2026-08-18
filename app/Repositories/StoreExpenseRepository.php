<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\StoreExpense;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\StoreExpenseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StoreExpenseRepository extends BaseRepository implements BranchScopedInterface, StoreExpenseRepositoryInterface
{
    protected function model(): string
    {
        return StoreExpense::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['description', 'category.name'])),
            'branch_id',
            'expense_category_id',
            'paid_from',
            AllowedFilter::callback(
                'incurred_from',
                fn ($query, $value) => $query->whereDate('incurred_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'incurred_to',
                fn ($query, $value) => $query->whereDate('incurred_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'incurred_at', 'amount'];
    }

    protected function allowedIncludes(): array
    {
        return ['category', 'branch', 'session'];
    }

    protected function defaultSort(): string
    {
        return '-incurred_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['category', 'branch']);
    }

    public function betweenDates(int $branchId, string $from, string $to): Collection
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->whereDate('incurred_at', '>=', $from)
            ->whereDate('incurred_at', '<=', $to)
            ->with('category')
            ->get();
    }
}
