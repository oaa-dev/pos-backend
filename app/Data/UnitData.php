<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UnitData extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|null|Optional $abbreviation,
    ) {}
}
