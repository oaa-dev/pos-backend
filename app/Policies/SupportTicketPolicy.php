<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

/**
 * `show`, `update` and `reply` resolve their ticket through route-model
 * binding, so they never reach `SupportTicketRepository` and its store rule
 * does not protect them. Without this, any holder of `support.view` could read
 * another customer's tickets by guessing an id.
 *
 * **The method names are load-bearing.** `Gate::before` grants any ability
 * whose name matches a permission the user holds, and returns before the policy
 * runs. A method named `support.view` would therefore be granted outright and
 * this file would never execute. Bare verbs cannot collide with the catalogue's
 * `module.action` shape, which is why the naming split exists.
 */
class SupportTicketPolicy
{
    /**
     * The operator sees everything; everyone else sees their own store.
     *
     * `stores.view` is platform-level and excluded from the `owner` wildcard,
     * so it is a marker no customer can acquire.
     */
    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->can('stores.view')
            || ($user->store?->id !== null && $ticket->store_id === $user->store->id);
    }

    /** Both sides of the conversation may write to it. */
    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Status and priority are the operator's call.
     *
     * An owner marking their own ticket resolved would tell support nothing,
     * and letting them raise priority on their own report makes the field
     * meaningless.
     */
    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->can('stores.view');
    }
}
