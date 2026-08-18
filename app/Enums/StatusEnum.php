<?php

namespace App\Enums;

enum StatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
