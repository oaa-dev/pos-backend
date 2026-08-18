<?php

namespace App\Exceptions;

use Exception;

class CreditLimitExceededException extends Exception
{
    public function __construct(
        string $message = 'This purchase would exceed the customer credit limit.',
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
