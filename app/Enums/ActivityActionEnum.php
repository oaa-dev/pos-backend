<?php

namespace App\Enums;

/**
 * The curated actions — the ones a human reason attaches to.
 *
 * **The `action` column is a plain string, not this enum.** The `LogsActivity`
 * trait writes `{model}.{event}` mechanically for any model it is applied to,
 * so forcing every value through here would mean an unmapped model throwing at
 * runtime — inside the transaction the logger must never break.
 *
 * These are the decisions where *why* matters as much as *what*: a void, a
 * write-off, an adjustment. `ActivityLogger::record()` takes this enum so those
 * few cannot be misspelt and the filter list on the screen is exhaustive; the
 * trait's mechanical entries sit alongside them as free strings.
 */
/*
 * Four cases, not six. `stock.adjusted` and `shift.closed` were planned and
 * then dropped during implementation: `StockAdjustment` and `CashDrawerSession`
 * both carry `LogsActivity`, and the trait's own row already holds everything
 * an explicit call would have written — the adjustment's `reason` and `note`,
 * the session's `variance`, `expected_cash` and `closing_counted`. Adding a
 * second call there would have meant suppressing the trait only to re-record
 * the same fields under a nicer name.
 *
 * What remains are the four subjects with **no trait**: `Sale`, `SaleReturn`
 * and `CreditTransaction` are all on the till's hot path, where a trait would
 * log every sale and bury the entries worth reading.
 */
enum ActivityActionEnum: string
{
    case SALE_VOIDED = 'sale.voided';
    case SALE_RETURNED = 'sale.returned';
    case CREDIT_WRITTEN_OFF = 'credit.written-off';
    case CREDIT_ADJUSTED = 'credit.adjusted';

    /** For the screen's filter dropdown. */
    public function label(): string
    {
        return match ($this) {
            self::SALE_VOIDED => 'Sale voided',
            self::SALE_RETURNED => 'Sale returned',
            self::CREDIT_WRITTEN_OFF => 'Utang written off',
            self::CREDIT_ADJUSTED => 'Utang adjusted',
        };
    }
}
