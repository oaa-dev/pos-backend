<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    protected function model(): string
    {
        return Unit::class;
    }

    protected function allowedFilters(): array
    {
        return [
            AllowedFilter::custom('q', new GlobalSearchFilter(['name', 'abbreviation'])),
            'name',
            'abbreviation',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    /**
     * Every live unit, ordered by name, for a select.
     *
     * Deliberately not `paginate()`: a dropdown that pages is a dropdown that
     * silently omits units, and the caller has no say over this query — which
     * is what `builder()` is for. Retired units stay out; a product already
     * sold in one renders its own history through `withTrashed()` elsewhere.
     */
    public function dropdown(): Collection
    {
        return $this->builder()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'allows_fraction']);
    }
}
