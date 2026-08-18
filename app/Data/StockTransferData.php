<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class StockTransferData extends Data
{
    public function __construct(
        public int $from_branch_id,
        public int $to_branch_id,
        /** @var list<StockTransferItemData> */
        public array $items,
        public string|null|Optional $notes = new Optional,
    ) {}
}
