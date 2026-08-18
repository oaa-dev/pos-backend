<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\City;
use App\Repositories\Contracts\CityRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class CityRepository extends BaseRepository implements CityRepositoryInterface
{
    protected function model(): string
    {
        return City::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'psgc_code'])),
            'name',
            'psgc_code',
            'province_id',
            'region_id',
            AllowedFilter::exact('is_city'),
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'psgc_code'];
    }

    protected function allowedIncludes(): array
    {
        return ['province', 'region', 'barangays'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
