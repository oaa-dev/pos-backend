<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\DiscountType;
use App\Repositories\Contracts\DiscountTypeRepositoryInterface;
use Spatie\QueryBuilder\AllowedFilter;

class DiscountTypeRepository extends BaseRepository implements DiscountTypeRepositoryInterface
{
    protected function model(): string
    {
        return DiscountType::class;
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

    protected function defaultSort(): string
    {
        return 'name';
    }

    public function findApplicable(string $slug): ?DiscountType
    {
        return $this->builder()->active()->where('slug', $slug)->first();
    }
}
