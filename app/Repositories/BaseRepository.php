<?php

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use App\Repositories\Contracts\BranchScopedInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseRepository implements BaseRepositoryInterface
{
    abstract protected function model(): string;

    abstract protected function allowedFilters(): array;

    protected function allowedSorts(): array
    {
        return ['id', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return [];
    }

    protected function defaultSort(): string
    {
        return '-created_at';
    }

    protected function query(): QueryBuilder
    {
        $query = QueryBuilder::for($this->model())
            ->allowedFilters(...$this->allowedFilters())
            ->allowedSorts(...$this->allowedSorts())
            ->allowedIncludes(...$this->allowedIncludes())
            ->defaultSort($this->defaultSort());

        if ($this instanceof BranchScopedInterface) {
            $this->applyBranchScope($query);
        }

        return $query;
    }

    /**
     * Narrows a listing to the branches the current user is assigned to.
     *
     * Deliberately a no-op without an authenticated user: seeders, queued jobs
     * and artisan commands run outside a request and have no branch to scope
     * to, and assuming otherwise fatals there.
     */
    protected function applyBranchScope(QueryBuilder $query): void
    {
        $user = auth()->user();

        if ($user === null || $user->can('branches.view-all')) {
            return;
        }

        $query->whereIn(
            $this->branchColumn(),
            $user->branches()->pluck('branches.id'),
        );
    }

    public function builder(): Builder
    {
        return $this->model()::query();
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns)->withQueryString();
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->builder()->get($columns);
    }

    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->builder()->find($id, $columns);
    }

    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        return $this->builder()->findOrFail($id, $columns);
    }

    public function findById(int $id): Model
    {
        return $this->findOrFail($id);
    }

    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return $this->builder()->where($field, $value)->first($columns);
    }

    public function findAllBy(string $field, mixed $value, array $columns = ['*']): Collection
    {
        return $this->builder()->where($field, $value)->get($columns);
    }

    public function create(array $data): Model
    {
        return $this->model()::create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }
}
