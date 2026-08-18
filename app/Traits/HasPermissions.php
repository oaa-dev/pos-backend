<?php

namespace App\Traits;

use Illuminate\Support\Collection;

/**
 * Resolves a user's abilities from the single role on `users.role_id`.
 *
 * A user with no role has no permissions and is denied everything — the
 * default-deny is deliberate, and is what makes adding `can:` to a route
 * safe by construction.
 */
trait HasPermissions
{
    private ?Collection $cachedPermissionNames = null;

    public function permissionNames(): Collection
    {
        if ($this->cachedPermissionNames !== null) {
            return $this->cachedPermissionNames;
        }

        // Memoised per instance: a single request runs one Gate check per
        // guarded route, but a controller or resource may run several more,
        // and each would otherwise re-query role.permissions.
        $this->loadMissing('role.permissions');

        return $this->cachedPermissionNames = $this->role?->permissions->pluck('name') ?? collect();
    }

    public function hasPermissionTo(string $ability): bool
    {
        return $this->permissionNames()->contains($ability);
    }

    /**
     * Drops the memoised set after the user's role or its permissions change
     * within the same request.
     */
    public function forgetCachedPermissions(): static
    {
        $this->cachedPermissionNames = null;
        $this->unsetRelation('role');

        return $this;
    }
}
