<?php

namespace App\Domain\Network\Commerce;

final class PlatformWebhookValidationResult
{
    public const PAYMENT_ID_MISSING = 'PAYMENT_ID_MISSING';
    public const PAYMENT_ID_MISMATCH = 'PAYMENT_ID_MISMATCH';
    public const SECRET_MISSING = 'SECRET_MISSING';
    public const SIGNATURE_HEADER_MISSING = 'SIGNATURE_HEADER_MISSING';
    public const REQUEST_ID_MISSING = 'REQUEST_ID_MISSING';
    public const TIMESTAMP_MISSING = 'TIMESTAMP_MISSING';
    public const V1_MISSING = 'V1_MISSING';
    public const TIMESTAMP_INVALID = 'TIMESTAMP_INVALID';
    public const WEBHOOK_STALE = 'WEBHOOK_STALE';
    public const HMAC_MISMATCH = 'HMAC_MISMATCH';
    public const VALID_SIGNATURE = 'VALID_SIGNATURE';

    private function __construct() {}
}
