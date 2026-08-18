<?php

namespace App\Exceptions;

use Exception;

class InvalidCredentialsException extends Exception
{
    protected int $statusCode = 401;

    public function __construct(string $message = 'Invalid credentials')
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
