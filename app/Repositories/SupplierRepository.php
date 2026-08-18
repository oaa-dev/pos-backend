<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'store_id',
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'name', 'contact_person', 'phone', 'email',
            ])),
            'name',
            'status',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['address', 'products'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    protected function query(): QueryBuilder
    {
        return parent::query()->with('address');
    }
}
