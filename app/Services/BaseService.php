<?php

namespace App\Services;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseService
{
    public function __construct(
        protected readonly BaseRepositoryInterface $repository
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    public function findById(int $id): Model
    {
        return $this->repository->findById($id);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        return $this->repository->update($model, $data);
    }

    public function delete(Model $model): void
    {
        $this->repository->delete($model);
    }
}
