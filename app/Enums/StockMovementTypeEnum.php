<?php

namespace App\Enums;

enum StockMovementTypeEnum: string
{
    case OPENING_BALANCE = 'opening_balance';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case SALE = 'sale';
    case SALE_VOID = 'sale_void';
    case RETURN_IN = 'return_in';
    case SUPPLIER_RETURN_OUT = 'supplier_return_out';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case SPOILAGE = 'spoilage';
    case EXPIRY_WRITEOFF = 'expiry_writeoff';
    case PERSONAL_USE = 'personal_use';
    case FREEBIE = 'freebie';
    case REPACK_IN = 'repack_in';
    case REPACK_OUT = 'repack_out';

    /**
     * Whether this type adds stock. Movements store a signed
     * `quantity_base`, and this is what decides the sign.
     */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::OPENING_BALANCE,
            self::PURCHASE_RECEIPT,
            self::SALE_VOID,
            self::RETURN_IN,
            self::ADJUSTMENT_IN,
            self::TRANSFER_IN,
            self::REPACK_IN,
        ], true);
    }

    /**
     * Outbound types that are *losses* rather than sales — these are what a
     * shrinkage report sums, and personal use and freebies are deliberately
     * excluded because they are choices, not losses.
     */
    public function isShrinkage(): bool
    {
        return in_array($this, [
            self::SPOILAGE,
            self::EXPIRY_WRITEOFF,
        ], true);
    }
}
