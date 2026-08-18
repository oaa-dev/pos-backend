<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `store_users` join row.
 *
 * A model rather than a bare pivot table because `Store::owner()` and
 * `User::store()` reach through it with `hasOneThrough`, which needs a class
 * to name. Going through the pivot is what makes those relations
 * eager-loadable — `Store::with('owner')` over the whole index resolves each
 * store's own owner, which a `whereExists` subquery bound to one parent id
 * would not.
 *
 * `users` carries no `store_id`: a person has one login, and this table is the
 * only record of which store they belong to and whether they own it.
 */
class StoreUser extends Pivot
{
    protected $table = 'store_users';

    public $incrementing = true;

    protected $fillable = [
        'store_id',
        'user_id',
        'is_owner',
    ];

    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
        ];
    }
}
