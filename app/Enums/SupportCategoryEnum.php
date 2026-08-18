<?php

namespace App\Enums;

enum SupportCategoryEnum: string
{
    case BUG = 'bug';
    case QUESTION = 'question';
    case BILLING = 'billing';
    case FEATURE = 'feature';

    public function label(): string
    {
        return match ($this) {
            self::BUG => 'Something is broken',
            self::QUESTION => 'Question',
            self::BILLING => 'Billing',
            self::FEATURE => 'Feature request',
        };
    }
}
