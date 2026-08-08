<?php

namespace App\Domain\Network\Channels\B2C\Exceptions;

use RuntimeException;

final class TenantQuoteUnavailableException extends RuntimeException
{
    public static function noCommercialRates(): self
    {
        return new self('No commercial tenant rates were available.');
    }
}
