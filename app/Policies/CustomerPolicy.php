<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/**
 * Repository scoping narrows listings only; route-model binding resolves a
 * customer directly. Without this a tindera could read another branch's suki
 * by guessing an id.
 *
 * Abilities stay bare verbs so Gate::before (which grants on catalogue
 * permission names like `customers.view`) cannot short-circuit them.
 */
class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $this->isVisibleTo($user, $customer);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->isVisibleTo($user, $customer);
    }

    private function isVisibleTo(User $user, Customer $customer): bool
    {
        if ($user->can('branches.view-all')) {
            return true;
        }

        // A customer with no branch is store-wide — an utang that follows the
        // suki rather than the counter they happened to use.
        if ($customer->branch_id === null) {
            return true;
        }

        return $user->branches()->whereKey($customer->branch_id)->exists();
    }
}
