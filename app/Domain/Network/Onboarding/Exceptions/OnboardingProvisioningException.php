<?php

namespace App\Domain\Network\Onboarding\Exceptions;

use RuntimeException;

final class OnboardingProvisioningException extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct($failureCode);
    }
}
