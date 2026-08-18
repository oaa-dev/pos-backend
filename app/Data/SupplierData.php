<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A supplier's editable fields.
 *
 * `SupplierService` used to take `$request->validated()` verbatim and hand it
 * straight to `create()`, with mass assignment as the only thing deciding what
 * was accepted. Enumerating them here is the point of the class.
 *
 * `terms_days` is deliberately absent: the column exists but is neither
 * `$fillable` nor in the request, so it has never been settable. Adding it is
 * a feature, not part of this conversion.
 */
class SupplierData extends Data
{
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $name = new Optional,
        public string|null|Optional $contact_person = new Optional,
        public string|null|Optional $phone = new Optional,
        public string|null|Optional $email = new Optional,
        public string|null|Optional $notes = new Optional,
        public string|Optional $status = new Optional,
    ) {}
}
