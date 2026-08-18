<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Everything needed to stand up a new tenant: the shop, and the person who
 * will own it. The two arrive together because neither is usable alone — a
 * store with no owner cannot be logged into, and a user with no store has
 * nowhere to put a product.
 */
class StoreRegistrationData extends Data
{
    public function __construct(
        public string $firstname,
        public string $lastname,
        public string $email,
        public string $password,
        public ?string $store_name = null,
        public ?string $phone_number = null,
    ) {}
}
