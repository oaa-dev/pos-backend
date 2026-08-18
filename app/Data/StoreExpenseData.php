<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * One recorded store expense.
 *
 * `store_id` is accepted for symmetry with the other store-owned forms, but
 * `store_expenses` has no such column — the expense reaches its store through
 * `branch_id`. It is validated and then dropped, which is what the array form
 * did too.
 *
 * `recorded_by` is absent on purpose: it comes from the authenticated user in
 * the service, never from the request.
 */
class StoreExpenseData extends Data
{
    public function __construct(
        public int|Optional $branch_id = new Optional,
        public int|Optional $expense_category_id = new Optional,
        public string|float|int|Optional $amount = new Optional,
        public string|null|Optional $description = new Optional,
        /** Only `drawer` affects the shift count. */
        public string|null|Optional $paid_from = new Optional,
        public int|null|Optional $supplier_id = new Optional,
        public string|null|Optional $incurred_at = new Optional,
    ) {}
}
