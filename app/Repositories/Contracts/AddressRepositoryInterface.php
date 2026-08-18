<?php

namespace App\Repositories\Contracts;

use App\Models\Address;
use Illuminate\Database\Eloquent\Model;

interface AddressRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Upsert the owner's address of a given type through its morph relation.
     *
     * @param  array<string, mixed>  $values
     */
    public function upsertForOwner(Model $owner, string $type, array $values): Address;
}
