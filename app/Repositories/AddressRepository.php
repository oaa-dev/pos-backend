<?php

namespace App\Repositories;

use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AddressRepository extends BaseRepository implements AddressRepositoryInterface
{
    protected function model(): string
    {
        return Address::class;
    }

    /**
     * Addresses are never listed on their own — they are reached through the
     * model that owns them — so the Spatie pipeline goes unused here. The
     * abstract still has to be satisfied.
     */
    protected function allowedFilters(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function upsertForOwner(Model $owner, string $type, array $values): Address
    {
        return $owner->address()->updateOrCreate(['type' => $type], $values);
    }
}
