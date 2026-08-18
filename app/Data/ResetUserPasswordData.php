<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ResetUserPasswordData extends Data
{
    public function __construct(public string $new_password) {}
}
