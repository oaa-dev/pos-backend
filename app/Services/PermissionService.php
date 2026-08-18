<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PermissionService extends BaseService
{
    public function __construct(
        protected readonly PermissionGroupRepositoryInterface $permissionGroupRepository
    ) {
        parent::__construct($permissionGroupRepository);
    }

    /**
     * The whole catalogue, grouped.
     *
     * Deliberately unfiltered. This used to hide permissions the actor did not
     * hold, which made the role editor show a different catalogue to different
     * people and left `superadmin` unable to see what it was granting. Who may
     * read this at all is settled by `can:roles.view` on the route.
     */
    public function groupedByModule(): Collection
    {
        return $this->permissionGroupRepository->allWithPermissions();
    }
}
