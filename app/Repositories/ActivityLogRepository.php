<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Deliberately **neither** `StoreScopedInterface` nor `BranchScopedInterface`.
 *
 * The audience is the platform operator: this listing is guarded by
 * `activity-logs.view`, which lives in `PermissionSeeder::PLATFORM_MODULES` and
 * so never reaches an owner. Narrowing it to the actor's own store would leave
 * the only person allowed to read it able to read almost none of it.
 *
 * `store_id` is therefore a **filter the caller chooses**, not a scope applied
 * to them.
 */
class ActivityLogRepository extends BaseRepository implements ActivityLogRepositoryInterface
{
    protected function model(): string
    {
        return ActivityLog::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'action',
                'reason',
                'causer.name',
            ])),
            'action',
            'user_id',
            'store_id',
            'auditable_type',

            // Mirrors StockMovementRepository's occurred_from/occurred_to pair,
            // which is the shape the datatable's date-range field serialises to.
            AllowedFilter::callback(
                'occurred_from',
                fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value),
            ),
            AllowedFilter::callback(
                'occurred_to',
                fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value),
            ),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'action', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['causer', 'store', 'auditable'];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with(['causer', 'store']);
    }
}
