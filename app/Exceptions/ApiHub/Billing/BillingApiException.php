<?php

namespace App\Exceptions\ApiHub\Billing;

use RuntimeException;

class BillingApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $statusCode = 422,
        private readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function details(): array
    {
        return $this->details;
    }
}
