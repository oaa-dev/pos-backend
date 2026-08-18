<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ProductUnitData extends Data
{
    public function __construct(
        public int $unit_id,
        public float $conversion_factor,
        public float $selling_price,
        public bool $is_base = false,
        public bool $is_default_sale_unit = false,
        public int $sort_order = 0,
        public int|Optional $id = new Optional,
        public string|null|Optional $barcode = new Optional,
    ) {}
}
