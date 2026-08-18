<?php

namespace App\Exceptions;

use Exception;

class SaleAlreadyVoidedException extends Exception
{
    public function __construct(
        string $message = 'That sale has already been voided.',
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
