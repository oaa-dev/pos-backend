<?php

namespace App\Enums;

/**
 * A ticket's lifecycle.
 *
 * Deliberately **not** `StatusEnum`, which is the generic active/inactive one.
 * A ticket is never "inactive"; it is open, being worked, or answered.
 */
enum SupportTicketStatusEnum: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In progress',
            self::RESOLVED => 'Resolved',
        };
    }
}
