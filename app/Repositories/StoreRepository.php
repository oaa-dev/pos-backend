<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Store;
use App\Repositories\Contracts\StoreRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Eloquent access to the tenant record itself.
 *
 * **This listing returns every paying customer's store.** `Store` carries no
 * tenant scope — it *is* the tenant — so nothing narrows `paginate()` the way
 * a global scope narrows every other repository in this application. Its only
 * legitimate caller is a route guarded by `can:stores.*`. Injecting
 * `StoreRepositoryInterface` anywhere else, or reaching for it from a
 * customer-facing endpoint, is a cross-tenant read.
 *
 * The owner's own store is not served from here at all: `StoreService` resolves
 * it from the authenticated user, and `/store` takes no id.
 *
 * Soft-deleted (closed) stores are excluded by `SoftDeletes`.
 */
class StoreRepository extends BaseRepository implements StoreRepositoryInterface
{
    protected function model(): string
    {
        return Store::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'name', 'slug', 'owner_name', 'phone',
            ])),
            'status',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function query(): QueryBuilder
    {
        // Eager-loaded because the index renders the owner in a column, and
        // "no owner yet" is the state the screen exists to surface.
        return parent::query()->with('owner.profile');
    }
}
