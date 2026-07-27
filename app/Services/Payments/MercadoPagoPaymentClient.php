<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentVerificationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MercadoPagoPaymentClient
{
    public function find(string $paymentId): array
    {
        $paymentId = trim($paymentId);

        if ($paymentId === '' || !preg_match('/^[0-9]+$/', $paymentId)) {
            throw new PaymentVerificationException(
                'INVALID_PAYMENT_ID',
                'El identificador de pago recibido no es válido.'
            );
        }

        $accessToken = trim((string) config(
            'services.mercadopago.access_token'
        ));

        if ($accessToken === '') {
            throw new PaymentVerificationException(
                'MISSING_ACCESS_TOKEN',
                'Mercado Pago no está configurado correctamente.'
            );
        }

        $baseUrl = rtrim(
            (string) config(
                'services.mercadopago.api_base_url',
                'https://api.mercadopago.com'
            ),
            '/'
        );

        try {
            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->connectTimeout((int) config(
                    'services.mercadopago.connect_timeout',
                    5
                ))
                ->timeout((int) config(
                    'services.mercadopago.timeout',
                    15
                ))
                ->get($baseUrl . '/v1/payments/' . $paymentId);
        } catch (ConnectionException $exception) {
            throw new PaymentVerificationException(
                'CONNECTION_ERROR',
                'No fue posible comunicarse con Mercado Pago.'
            );
        }

        if ($response->status() === 404) {
            throw new PaymentVerificationException(
                'PAYMENT_NOT_FOUND',
                'Mercado Pago no encontró el pago informado.'
            );
        }

        if (!$response->successful()) {
            throw new PaymentVerificationException(
                'PROVIDER_ERROR',
                'Mercado Pago respondió con un error al verificar el pago.'
            );
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new PaymentVerificationException(
                'INVALID_PROVIDER_RESPONSE',
                'Mercado Pago devolvió una respuesta inválida.'
            );
        }

        return $payload;
    }
}
