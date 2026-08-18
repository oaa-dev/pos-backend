<?php

namespace App\Exceptions;

use Exception;

class InvalidApprovalPinException extends Exception
{
    public function __construct(
        string $message = 'That approval PIN is not valid for this action.',
        private readonly int $statusCode = 403,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
