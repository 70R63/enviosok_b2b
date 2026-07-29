<?php

namespace App\Exceptions\Identity;

use RuntimeException;

class IdentityVerificationRequiredException extends RuntimeException
{
    public function __construct(
        private array $assessment
    ) {
        parent::__construct(
            (string) (
                $assessment['message']
                ?? 'Necesitas verificar tu identidad para continuar.'
            )
        );
    }

    public function assessment(): array
    {
        return $this->assessment;
    }
}
