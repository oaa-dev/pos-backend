<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\SaleSuspension;
use App\Repositories\Contracts\BranchScopedInterface;
use App\Repositories\Contracts\SaleSuspensionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SaleSuspensionRepository extends BaseRepository implements BranchScopedInterface, SaleSuspensionRepositoryInterface
{
    protected function model(): string
    {
        return SaleSuspension::class;
    }

    public function branchColumn(): string
    {
        return 'branch_id';
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['label'])),
            'branch_id',
            'status',
            'user_id',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'suspended_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['branch', 'user'];
    }

    protected function defaultSort(): string
    {
        return '-suspended_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with('user');
    }

    public function suspendedForBranch(int $branchId): Collection
    {
        return $this->builder()
            ->where('branch_id', $branchId)
            ->suspended()
            ->where(
                'suspended_at',
                '>=',
                now()->subMinutes(SaleSuspension::RESUME_WINDOW_MINUTES),
            )
            ->with('user')
            ->latest('suspended_at')
            ->get();
    }
}
