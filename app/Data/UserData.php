<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserData extends Data
{
    /**
     * Every field is Optional so one DTO can carry both a full create and a
     * partial update, matching how UserProfileData is used. What a create
     * requires is enforced by StoreUserRequest, not by this shape.
     */
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $firstname = new Optional,
        public string|Optional $lastname = new Optional,
        public string|Optional $email = new Optional,
        public string|Optional $password = new Optional,
        public string|Optional $password_confirmation = new Optional,
        public int|null|Optional $role_id = new Optional,
        public string|null|Optional $phone_number = new Optional,
        public string|null|Optional $birthdate = new Optional,
    ) {}
}
