<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class StockTransferItemData extends Data
{
    public function __construct(
        public int $product_id,
        public int $product_unit_id,
        public string|float|int $quantity,
    ) {}
}
