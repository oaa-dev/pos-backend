<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A store's editable fields.
 *
 * One DTO for both paths — this absorbed `ManagedStoreData`, which differed
 * only by carrying `description` and `status`. Which of these fields a caller
 * may actually set is decided by the **request class**, not by the shape here:
 * `UpdateStoreRequest` (the owner's own store) omits `status`, so a store
 * cannot un-suspend itself, while `UpdateManagedStoreRequest` allows it.
 *
 * `slug` is absent from both. It is the one globally unique handle a store
 * has, and anything that ever addresses a store by it — a subdomain, a public
 * receipt URL — breaks when it moves. It is derived on create and never
 * changes.
 */
class StoreData extends Data
{
    public function __construct(
        public string|Optional $name = new Optional,
        public string|null|Optional $description = new Optional,
        public string|null|Optional $owner_name = new Optional,
        public string|null|Optional $phone = new Optional,
        public string|Optional $status = new Optional,
    ) {}
}
