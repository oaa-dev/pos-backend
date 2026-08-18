<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    protected function model(): string
    {
        return Role::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'slug'])),
            'name',
            'slug',
            'status',
            'is_system',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'slug', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['permissions'];
    }

    /**
     * The list view shows how many permissions each role grants, so the count
     * is always present — `permissions` itself stays an opt-in include.
     */
    protected function query(): QueryBuilder
    {
        return parent::query()->withCount('permissions');
    }

    /**
     * Every role, ordered by name, for a select.
     *
     * Deliberately not `paginate()` and deliberately without the permission
     * count: a picker needs a label and an id, and pulling `permissions_count`
     * here would put a subquery on a request that runs on three screens. The
     * set matches what the picker showed before it moved off `index()`, so
     * nothing appears or disappears from the list.
     */
    public function dropdown(): Collection
    {
        return $this->builder()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_system', 'status']);
    }

    public function findBySlug(string $slug): ?Role
    {
        return $this->findBy('slug', $slug);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $role->permissions()->sync($permissionIds);

        return $role;
    }
}
