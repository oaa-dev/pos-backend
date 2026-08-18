<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Province;
use App\Repositories\Contracts\ProvinceRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class ProvinceRepository extends BaseRepository implements ProvinceRepositoryInterface
{
    protected function model(): string
    {
        return Province::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'psgc_code'])),
            'name',
            'psgc_code',
            'region_id',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'psgc_code'];
    }

    protected function allowedIncludes(): array
    {
        return ['region', 'cities'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
