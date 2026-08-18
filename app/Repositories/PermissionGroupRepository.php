<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\PermissionGroup;
use App\Repositories\Contracts\PermissionGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;

class PermissionGroupRepository extends BaseRepository implements PermissionGroupRepositoryInterface
{
    protected function model(): string
    {
        return PermissionGroup::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'description'])),
            'name',
            'status',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name'];
    }

    protected function allowedIncludes(): array
    {
        return ['permissions'];
    }

    protected function defaultSort(): string
    {
        return 'id';
    }

    /**
     * The catalogue is 12 groups / 51 permissions, so this is deliberately
     * unpaginated — the role editor needs the whole grid at once.
     */
    public function allWithPermissions(): Collection
    {
        return $this->builder()
            ->with('permissions')
            ->orderBy('id')
            ->get();
    }
}
