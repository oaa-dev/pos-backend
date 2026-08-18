<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SaleReturnItemData extends Data
{
    public function __construct(
        public int $sale_item_id,
        public string|float|int $quantity,
        /** Whether the item goes back on the shelf or is written off. */
        public bool $restock,
    ) {}
}
