<?php

namespace App\Enums;

enum AccountStatusEnum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case BLOCKED = 'blocked';
}
