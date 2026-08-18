<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class RoleData extends Data
{
    /**
     * `permissions` carries permission **ids**, not names — the role editor is
     * populated from `GET /permissions`, which hands ids back, and ids survive
     * a rename of the catalogue.
     *
     * @param  list<int>|Optional  $permissions
     */
    public function __construct(
        public string|Optional $name = new Optional,
        public string|Optional $slug = new Optional,
        public array|Optional $permissions = new Optional,
    ) {}
}
