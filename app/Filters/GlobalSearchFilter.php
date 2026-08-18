<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class GlobalSearchFilter implements Filter
{
    public function __construct(protected array $columns = []) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $search = is_array($value) ? implode(' ', $value) : $value;

        $query->where(function (Builder $query) use ($search) {
            foreach ($this->columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $relationColumn] = explode('.', $column, 2);
                    $query->orWhereHas($relation, function (Builder $q) use ($relationColumn, $search) {
                        $q->where($relationColumn, 'like', "%{$search}%");
                    });
                } else {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
    }
}
