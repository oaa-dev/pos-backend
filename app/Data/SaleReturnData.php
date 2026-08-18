<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * A return against an existing sale.
 *
 * The `Sale` itself stays a bound model argument on the service — it comes
 * from route-model binding, not from the payload.
 */
class SaleReturnData extends Data
{
    public function __construct(
        public string $reason,
        /** cash, credit, or exchange. */
        public string $refund_method,
        /** @var list<SaleReturnItemData> */
        public array $items,
    ) {}
}
