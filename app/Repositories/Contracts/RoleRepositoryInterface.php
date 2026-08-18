<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Every role, ordered by name, for a select.
     *
     * @return Collection<int, Role>
     */
    public function dropdown(): Collection;

    public function findBySlug(string $slug): ?Role;

    /**
     * @param  list<int>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): Role;
}
