<?php

namespace App\Enums;

enum SupportPriorityEnum: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'Urgent',
        };
    }
}
