<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ReportFilterData extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?int $product_id = null,
        public ?int $customer_id = null,
    ) {}
}
