<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ReorderFilterData extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        /**
         * How many days of stock the owner wants on the shelf.
         *
         * This stands in for supplier lead time, which exists as
         * `supplier_products.lead_time_days` but is zero on every row and
         * settable nowhere. A per-run target needs no data anyone has to
         * maintain, and lets the owner try 7 against 30 and see what moves.
         */
        public int $cover_target_days = 14,
    ) {}
}
