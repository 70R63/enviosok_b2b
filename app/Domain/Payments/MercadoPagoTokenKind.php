<?php

namespace App\Domain\Payments;

final class MercadoPagoTokenKind
{
    public static function detect(?string $token): string
    {
        if (str_starts_with((string) $token, 'TEST-')) return 'TEST';
        if (str_starts_with((string) $token, 'APP_USR-')) return 'APP_USR';

        return 'OTHER';
    }
}
