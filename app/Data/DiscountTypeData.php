<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A discount type's editable fields.
 *
 * Global, like units — `senior` and `pwd` are what the law says, so there is
 * no `store_id` here.
 *
 * `is_system` is deliberately absent: a row becomes statutory by being seeded,
 * never by being asked for. Including it would make the flag settable through
 * the API, which is exactly what the protected-field guard exists to prevent.
 */
class DiscountTypeData extends Data
{
    public function __construct(
        public string|Optional $name = new Optional,
        public string|null|Optional $slug = new Optional,
        /** Null means the amount is keyed in at the till rather than fixed. */
        public string|float|null|Optional $percentage = new Optional,
        public bool|Optional $requires_identification = new Optional,
        public string|Optional $status = new Optional,
    ) {}
}
