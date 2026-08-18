<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class StockAdjustmentData extends Data
{
    public function __construct(
        public int $branch_id,
        public string $reason,
        /** @var list<StockAdjustmentItemData> */
        public array $items,
        public ?string $note = null,
    ) {}
}
