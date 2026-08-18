<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * The person being made owner of an existing store.
 *
 * Separate from `StoreRegistrationData`, which carries the store *and* its
 * owner because self-registration creates both at once. Here the store already
 * exists — it was opened empty and is waiting for someone to run it.
 */
class StoreOwnerData extends Data
{
    public function __construct(
        public string $firstname,
        public string $lastname,
        public string $email,
        public string $password,
        public ?string $phone_number = null,
    ) {}
}
