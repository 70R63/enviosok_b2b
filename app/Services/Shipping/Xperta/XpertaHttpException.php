<?php

namespace App\Services\Shipping\Xperta;

use RuntimeException;

class XpertaHttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message
    ) {
        parent::__construct($message, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
