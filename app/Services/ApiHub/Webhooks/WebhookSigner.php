<?php

namespace App\Services\ApiHub\Webhooks;

use Illuminate\Support\Str;

class WebhookSigner
{
    public function generateSecret(): string
    {
        return 'zigo_whsec_' . Str::random(48);
    }

    public function sign(
        string $secret,
        int $timestamp,
        string $payloadJson
    ): string {
        return 'sha256=' . hash_hmac(
            'sha256',
            $timestamp . '.' . $payloadJson,
            $secret
        );
    }
}
