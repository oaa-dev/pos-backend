<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class StockAdjustmentItemData extends Data
{
    public function __construct(
        public int $product_id,
        public int $product_unit_id,
        /** Signed and non-zero: negative removes stock, positive adds it. */
        public string|float|int $quantity,
        public ?string $note = null,
    ) {}
}
