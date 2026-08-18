<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class CategoryData extends Data
{
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $name = new Optional,
        public string|Optional $slug = new Optional,
        public int|null|Optional $parent_id = new Optional,
    ) {}
}
