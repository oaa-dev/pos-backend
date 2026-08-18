<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/**
 * Repository branch scoping only narrows **listings**. Endpoints that resolve
 * a single branch through route-model binding never reach the repository, so
 * without this a tindera assigned to one branch could read or edit another by
 * guessing its id.
 *
 * These are model abilities (`view`, `update`), not catalogue permissions
 * (`branches.view`, `branches.update`). The names must stay distinct: Gate
 *::before grants any ability whose name matches a held permission and
 * short-circuits the policy, so a policy method sharing a permission's name
 * would never run.
 */
class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return $this->isAssignedTo($user, $branch);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->isAssignedTo($user, $branch);
    }

    private function isAssignedTo(User $user, Branch $branch): bool
    {
        if ($user->can('branches.view-all')) {
            return true;
        }

        return $user->branches()->whereKey($branch->getKey())->exists();
    }
}
