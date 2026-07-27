<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

class MercadoPagoWebhookSignatureValidator
{
    public function isValid(Request $request, string $dataId): bool
    {
        $secret = trim((string) config(
            'services.mercadopago.webhook_secret'
        ));

        if ($secret === '') {
            return false;
        }

        $signatureHeader = trim((string) $request->header(
            'x-signature'
        ));
        $requestId = trim((string) $request->header(
            'x-request-id'
        ));

        if (
            $signatureHeader === ''
            || $requestId === ''
            || trim($dataId) === ''
        ) {
            return false;
        }

        $timestamp = null;
        $receivedSignature = null;

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                null
            );

            if ($key === 'ts') {
                $timestamp = $value;
            }

            if ($key === 'v1') {
                $receivedSignature = $value;
            }
        }

        if (!$timestamp || !$receivedSignature) {
            return false;
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            strtolower(trim($dataId)),
            $requestId,
            $timestamp
        );

        $expectedSignature = hash_hmac(
            'sha256',
            $manifest,
            $secret
        );

        return hash_equals(
            $expectedSignature,
            $receivedSignature
        );
    }
}
