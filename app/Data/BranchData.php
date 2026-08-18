<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class BranchData extends Data
{
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $name = new Optional,
        public string|Optional $code = new Optional,
        public string|null|Optional $phone = new Optional,
        public string|Optional $status = new Optional,
        public string|null|Optional $opened_at = new Optional,
        public AddressData|Optional $address = new Optional,
    ) {}
}
