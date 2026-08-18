<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface PermissionGroupRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Every group with its permissions, ordered for display.
     */
    public function allWithPermissions(): Collection;
}
