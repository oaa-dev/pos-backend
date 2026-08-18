<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Region;
use App\Repositories\Contracts\RegionRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class RegionRepository extends BaseRepository implements RegionRepositoryInterface
{
    protected function model(): string
    {
        return Region::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'psgc_code'])),
            'name',
            'psgc_code',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'psgc_code'];
    }

    protected function allowedIncludes(): array
    {
        return ['provinces', 'cities'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
