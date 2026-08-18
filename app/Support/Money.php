<?php

namespace App\Support;

/**
 * Decimal arithmetic that rounds instead of truncating.
 *
 * bcmath has no rounding of its own — every operation truncates toward zero.
 * That is invisible on a single figure and systematic across a ledger: a box
 * of 12 at ₱100 truncates to ₱8.3333 a piece, which multiplies back to
 * ₱99.9996 against the ₱100.00 actually paid, and the shortfall recurs on
 * every delivery.
 *
 * This lives outside both StockService and SaleService because they were
 * rounding differently — receive() truncated while weightedUnitCost() rounded
 * — and two divergent rounding rules are worse than one truncating rule.
 */
final class Money
{
    /**
     * Round half away from zero at the given scale.
     *
     * Adding half a unit of the target scale before truncating gives the
     * expected behaviour: 8.33335 → 8.3334, and −8.33335 → −8.3334.
     */
    public static function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, str_starts_with($value, '-') ? '-'.$half : $half, $scale);
    }

    /**
     * Divide at a wider scale than needed, then round back.
     *
     * Rounding a division computed at the target scale rounds an already
     * truncated figure, which is not the same number — the intermediate has
     * to carry the digits the rounding decision depends on.
     */
    public static function divide(string $dividend, string $divisor, int $scale, int $intermediate = 8): string
    {
        if (bccomp($divisor, '0', $intermediate) === 0) {
            return bcadd('0', '0', $scale);
        }

        return self::round(bcdiv($dividend, $divisor, $intermediate), $scale);
    }
}
