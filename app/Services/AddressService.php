<?php

namespace App\Services;

use App\Data\AddressData;
use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AddressService
{
    public function __construct(
        protected readonly AddressRepositoryInterface $addressRepository
    ) {}

    public function upsertFor(Model $owner, AddressData $data): Address
    {
        if (! method_exists($owner, 'address')) {
            throw new \RuntimeException(
                sprintf('%s must declare an address(): MorphOne relation to receive an address.', get_class($owner))
            );
        }

        return $this->addressRepository->upsertForOwner($owner, $data->type, [
            'region_id' => $data->region_id,
            'province_id' => $data->province_id,
            'city_id' => $data->city_id,
            'barangay_id' => $data->barangay_id,
            'address_line' => $data->address_line,
            'postal_code' => $data->postal_code,
            'is_default' => $data->is_default,
        ]);
    }
}
