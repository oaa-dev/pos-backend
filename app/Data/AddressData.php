<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public int $region_id,
        public int $city_id,
        public int $barangay_id,
        public ?int $province_id = null,
        public ?string $address_line = null,
        public ?string $postal_code = null,
        public string $type = 'primary',
        public bool $is_default = true,
    ) {}
}
