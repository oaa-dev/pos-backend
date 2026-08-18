<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserProfileData extends Data
{
    public function __construct(
        public string|Optional $firstname = new Optional,
        public string|Optional $lastname = new Optional,
        public string|null|Optional $middlename = new Optional,
        public string|null|Optional $suffix = new Optional,
        public string|null|Optional $salutation = new Optional,
        public string|null|Optional $gender = new Optional,
        public string|null|Optional $birthdate = new Optional,
        public AddressData|Optional $address = new Optional,
    ) {}
}
