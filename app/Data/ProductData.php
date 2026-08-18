<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ProductData extends Data
{
    /**
     * @param  list<ProductUnitData>|Optional  $units
     */
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $name = new Optional,
        public string|null|Optional $sku = new Optional,
        public int|null|Optional $category_id = new Optional,
        public int|Optional $base_unit_id = new Optional,
        public bool|Optional $is_perishable = new Optional,
        public bool|Optional $is_favorite = new Optional,
        public int|Optional $favorite_sort = new Optional,
        public string|Optional $status = new Optional,
        public array|Optional $units = new Optional,
    ) {}
}
