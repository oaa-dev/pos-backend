<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\BranchScopedInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BranchRepository extends BaseRepository implements BranchRepositoryInterface, BranchScopedInterface
{
    protected function model(): string
    {
        return Branch::class;
    }

    /**
     * The branches table is its own branch key.
     */
    public function branchColumn(): string
    {
        return 'id';
    }

    protected function allowedFilters(): array
    {
        return [
            'store_id',
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'name',
                'code',
                'address.address_line',
            ])),
            'name',
            'code',
            'status',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'code', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['address', 'users'];
    }

    /**
     * BranchResource puts `address` behind whenLoaded(), and BaseRepository
     * eager-loads nothing, so without this the index silently returns rows
     * with no address key at all — and loading it per row would be an N+1.
     */
    protected function query(): QueryBuilder
    {
        return parent::query()
            ->with(['address'])
            ->withCount('users');
    }

    /**
     * The branches a select may offer this actor, ordered by name.
     *
     * Two narrowings, and both are needed. Branch assignment comes first, so a
     * tindera still sees only where she works — scoping by store alone would
     * hand her every branch in the business. Then the store: `filter[store_id]`
     * on `index()` narrows only when asked, and `applyBranchScope()` returns
     * early for anyone holding `branches.view-all`, so an owner's picker was
     * querying every customer's branches. Here her own store is applied
     * whether she asks or not, and a requested `$storeId` cannot override it.
     *
     * The platform operator belongs to no store and keeps the cross-store view
     * that the inventory and report screens already give them; `$storeId` is
     * how they narrow to one, and it is honoured only in that case.
     *
     * Because every row is bound to the caller this way, the route needs no
     * `can:` to be safe — unlike `index()`, whose whole result set is the same
     * for everyone who reaches it.
     */
    public function dropdown(?int $storeId = null): Collection
    {
        $query = $this->builder()->orderBy('name');

        $user = auth()->user();

        if ($user !== null && ! $user->can('branches.view-all')) {
            $query->whereIn('id', $user->branches()->pluck('branches.id'));
        }

        $effectiveStoreId = $user?->store?->id ?? $storeId;

        if ($effectiveStoreId !== null) {
            $query->where('store_id', $effectiveStoreId);
        }

        return $query->get(['id', 'name', 'code', 'store_id', 'status']);
    }

    public function findByCode(string $code): ?Branch
    {
        return $this->findBy('code', $code);
    }

    public function assignUser(Branch $branch, User $user, bool $isPrimary = false): Branch
    {
        $branch->users()->syncWithoutDetaching([
            $user->id => ['is_primary' => $isPrimary],
        ]);

        return $branch;
    }

    public function unassignUser(Branch $branch, User $user): Branch
    {
        $branch->users()->detach($user->id);

        return $branch;
    }
}
