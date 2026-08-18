<?php

namespace App\Enums;

/**
 * @deprecated Superseded by the `discount_types` table and `App\Models\DiscountType`.
 *
 * Discounts became data on 2026-08-06 so the owner can define a promo without
 * a migration. `SaleService` and `StoreSaleRequest` now resolve types by slug
 * against that table; the six cases here are seeded rows with matching slugs.
 *
 * Kept only because `StoreSaleSuspensionRequest` still validates against it in
 * work that has not landed yet. Delete this file once that request moves to
 * `Rule::exists('discount_types', 'slug')` — nothing else references it.
 */
enum SaleDiscountTypeEnum: string
{
    case MANUAL = 'manual';
    case PROMOTIONAL = 'promotional';
    case SENIOR = 'senior';
    case PWD = 'pwd';
    case EMPLOYEE = 'employee';
    case WHOLESALE = 'wholesale';

    /**
     * Whether the law requires an ID number, name, and signature on record.
     *
     * These are the two that go in the logbook.
     */
    public function requiresIdentification(): bool
    {
        return in_array($this, [self::SENIOR, self::PWD], true);
    }

    /**
     * Whether this type only applies to products flagged discount-eligible.
     *
     * The statutory 20% covers qualified goods, not every retail item, so it
     * is checked per line. A manual or wholesale discount is the owner's own
     * decision and applies to whatever they say.
     */
    public function respectsProductEligibility(): bool
    {
        return $this->requiresIdentification();
    }

    public function statutoryPercentage(): ?string
    {
        return $this->requiresIdentification() ? '20.00' : null;
    }
}
