<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

/**
 * Repository scoping narrows listings; route-model binding does not reach it.
 * Without this a tindera could read or void another branch's sale by id.
 */
class SalePolicy
{
    public function view(User $user, Sale $sale): bool
    {
        if ($user->can('branches.view-all')) {
            return true;
        }

        return $user->branches()->whereKey($sale->branch_id)->exists();
    }
}
