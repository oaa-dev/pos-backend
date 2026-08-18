<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CustomerData extends Data
{
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $name = new Optional,
        public string|null|Optional $nickname = new Optional,
        public string|null|Optional $phone = new Optional,
        public int|null|Optional $branch_id = new Optional,
        public bool|Optional $is_blocked = new Optional,
        public string|null|Optional $notes = new Optional,
    ) {}
}
