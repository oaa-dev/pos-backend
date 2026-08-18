<?php

namespace App\Data;

use App\Enums\SupportCategoryEnum;
use App\Enums\SupportPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Every field defaults to `Optional`, and the service filters on
 * `instanceof Optional`. Filtering on `!== null` instead would make a field
 * impossible to clear; filtering on nothing makes a PATCH of one field wipe
 * the rest.
 */
class SupportTicketData extends Data
{
    public function __construct(
        public int|Optional $store_id = new Optional,
        public string|Optional $subject = new Optional,
        public string|Optional $body = new Optional,
        public SupportCategoryEnum|Optional $category = new Optional,
        public SupportPriorityEnum|Optional $priority = new Optional,
        public SupportTicketStatusEnum|Optional $status = new Optional,
    ) {}
}
