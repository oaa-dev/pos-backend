<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Barangay;
use App\Repositories\Contracts\BarangayRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class BarangayRepository extends BaseRepository implements BarangayRepositoryInterface
{
    protected function model(): string
    {
        return Barangay::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'psgc_code'])),
            'name',
            'psgc_code',
            'city_id',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'psgc_code'];
    }

    protected function allowedIncludes(): array
    {
        return ['city'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }
}
