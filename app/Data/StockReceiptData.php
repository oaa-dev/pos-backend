<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * One ad-hoc stock receipt, outside the purchasing flow.
 *
 * `branch_id` stays on the DTO rather than being resolved to a `Branch` here:
 * the controller still has to load the branch and run `authorize('view', …)`
 * against it before anything reaches the service.
 */
class StockReceiptData extends Data
{
    public function __construct(
        public int $branch_id,
        public int $product_id,
        public int $product_unit_id,
        public string|float|int $quantity,
        public string|float|int|null $unit_cost = null,
        /** Naming either of these makes the stock-in a purchase. */
        public ?int $supplier_id = null,
        /** Only `drawer` charges the till. */
        public ?string $paid_from = null,
        public ?string $expiry_date = null,
        public ?string $batch_code = null,
        public ?string $note = null,
    ) {}
}
