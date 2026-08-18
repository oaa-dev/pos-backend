<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']): Collection;

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    public function find(int $id, array $columns = ['*']): ?Model;

    public function findOrFail(int $id, array $columns = ['*']): Model;

    public function findById(int $id): Model;

    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model;

    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;

    public function builder(): Builder;
}
