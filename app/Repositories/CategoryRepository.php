<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'store_id',
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'slug'])),
            'name',
            'slug',
            'parent_id',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['parent', 'children'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    /**
     * CategoryResource puts `parent` behind whenLoaded(), and BaseRepository
     * eager-loads nothing, so the key would otherwise vanish from every row.
     */
    protected function query(): QueryBuilder
    {
        return parent::query()->with(['parent']);
    }
}
