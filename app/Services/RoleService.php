<?php

namespace App\Services;

use App\Data\RoleData;
use App\Models\Permission;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;

class RoleService extends BaseService
{
    public function __construct(
        protected readonly RoleRepositoryInterface $roleRepository
    ) {
        parent::__construct($roleRepository);
    }

    /**
     * The select list, not the Roles screen.
     *
     * @return Collection<int, Role>
     */
    public function dropdown(): Collection
    {
        return $this->roleRepository->dropdown();
    }

    public function updateRole(Role $role, RoleData $data): Role
    {
        $this->guardSystemRoleIdentity($role, $data);

        return DB::transaction(function () use ($role, $data) {
            $values = array_filter([
                'name' => $data->name instanceof Optional ? null : $data->name,
                'slug' => $data->slug instanceof Optional ? null : $data->slug,
            ], fn ($value) => $value !== null);

            if ($values !== []) {
                $role = $this->roleRepository->update($role, $values);
            }

            if (! $data->permissions instanceof Optional) {
                $this->roleRepository->syncPermissions($role, $data->permissions);
            }

            return $role->load('permissions')->loadCount('permissions');
        });
    }

    /**
     * A system role's **permissions** are the operator's to tune; its
     * **identity** is not.
     *
     * SystemRoleSeeder matches on `slug`, so a renamed system role would be
     * orphaned and re-created as a duplicate on the next `db:seed`. The name
     * is locked alongside it so the two cannot drift apart.
     *
     * Note that permission edits to a system role *are* reset by a reseed,
     * since the seeder re-syncs every template. That is the accepted cost of
     * keeping these roles seeded.
     *
     * @throws AuthorizationException
     */
    private function guardSystemRoleIdentity(Role $role, RoleData $data): void
    {
        if (! $role->is_system) {
            return;
        }

        $renaming = ! $data->name instanceof Optional || ! $data->slug instanceof Optional;

        if ($renaming) {
            throw new AuthorizationException(
                'A system role\'s name and slug cannot be changed. Its permissions can.',
            );
        }
    }
}
