<?php

namespace App\Exceptions;

use Exception;

class ShiftNotOpenException extends Exception
{
    public function __construct(
        string $message = 'No cash drawer session is open for this branch.',
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
