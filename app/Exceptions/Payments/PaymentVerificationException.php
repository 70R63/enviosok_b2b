<?php

namespace App\Exceptions\Payments;

use RuntimeException;

class PaymentVerificationException extends RuntimeException
{
    public function __construct(
        private string $verificationCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function verificationCode(): string
    {
        return $this->verificationCode;
    }
}
