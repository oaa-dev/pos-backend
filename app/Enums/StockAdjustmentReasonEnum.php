<?php

namespace App\Enums;

enum StockAdjustmentReasonEnum: string
{
    case PHYSICAL_COUNT = 'physical_count';
    case SPOILAGE = 'spoilage';
    case EXPIRED = 'expired';
    case DAMAGED = 'damaged';
    case THEFT = 'theft';
    case PERSONAL_USE = 'personal_use';
    case FREEBIE = 'freebie';
    case SUPPLIER_RETURN = 'supplier_return';
    case CORRECTION = 'correction';

    /**
     * The movement type an adjustment of this reason emits, so that a
     * shrinkage report can separate real losses from owner consumption.
     */
    public function movementType(bool $inbound): StockMovementTypeEnum
    {
        if ($inbound) {
            return StockMovementTypeEnum::ADJUSTMENT_IN;
        }

        return match ($this) {
            self::SPOILAGE, self::DAMAGED => StockMovementTypeEnum::SPOILAGE,
            self::EXPIRED => StockMovementTypeEnum::EXPIRY_WRITEOFF,
            self::PERSONAL_USE => StockMovementTypeEnum::PERSONAL_USE,
            self::FREEBIE => StockMovementTypeEnum::FREEBIE,
            self::SUPPLIER_RETURN => StockMovementTypeEnum::SUPPLIER_RETURN_OUT,
            default => StockMovementTypeEnum::ADJUSTMENT_OUT,
        };
    }
}
