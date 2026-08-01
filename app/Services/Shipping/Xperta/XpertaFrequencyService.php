<?php

namespace App\Services\Shipping\Xperta;

use RuntimeException;

class XpertaFrequencyService
{
    public function __construct(
        private XpertaApiClient $client,
        private XpertaTokenService $tokenService
    ) {
    }

    public function check(
        string $originPostalCode,
        string $destinationPostalCode
    ): array {
        if (!config('services.xperta.frequency_enabled', true)) {
            return [
                'available' => true,
                'origin' => $originPostalCode,
                'destination' => $destinationPostalCode,
                'services' => [],
                'restriction' => null,
                'restriction_description' => null,
                'raw_status' => 'disabled',
                'provider_success' => null,
                'provider_message' => 'Consulta Frequency desactivada.',
                'response_code' => null,
                'raw_response' => [],
            ];
        }

        $originPostalCode = $this->normalizePostalCode(
            $originPostalCode
        );
        $destinationPostalCode = $this->normalizePostalCode(
            $destinationPostalCode
        );

        $path = $this->client->resolvePath(
            (string) config(
                'services.xperta.frequency_path',
                '/api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origen}/{destino}'
            ),
            [
                'corporativo' => config('services.xperta.corporativo'),
                'ltd' => config('services.xperta.ltd', 'estafeta'),
                'origen' => $originPostalCode,
                'destino' => $destinationPostalCode,
            ]
        );

        $response = $this->tokenService->withEncodedToken(
            fn (string $token) => $this->client->send(
                'POST',
                $path,
                ['token' => $token],
                $this->client->providerHeaders()
            )
        );

        return $this->normalize(
            $response,
            $originPostalCode,
            $destinationPostalCode
        );
    }

    private function normalize(
        array $response,
        string $origin,
        string $destination
    ): array
    {
        $data = data_get($response, 'data', []);

        if (!is_array($data)) {
            $data = [];
        }

        $services = data_get($data, 'services', []);
        if (!is_array($services)) {
            $services = [];
        }

        return [
            'available' => (bool) data_get($data, 'available', false),
            'origin' => (string) data_get($data, 'origin', $origin),
            'destination' => (string) data_get(
                $data,
                'destination',
                $destination
            ),
            'services' => array_values($services),
            'restriction' => $this->safeOptionalMessage(
                data_get($data, 'restriction')
            ),
            'restriction_description' => $this->safeOptionalMessage(
                data_get($data, 'restriction_description')
            ),
            'raw_status' => 'received',
            'provider_success' => array_key_exists('success', $response)
                ? (bool) $response['success']
                : null,
            'provider_message' => XpertaExternalMessageSanitizer::sanitize(
                $this->providerMessage($response)
            ),
            'response_code' => $this->safeResponseCode($response),
            'raw_response' => $response,
        ];
    }

    private function providerMessage(array $response): ?string
    {
        foreach ([
            data_get($response, 'message'),
            data_get($response, 'data.message'),
            data_get($response, 'error'),
            data_get($response, 'data.error'),
        ] as $message) {
            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        }

        return null;
    }

    private function safeOptionalMessage($message): ?string
    {
        if (!is_string($message) || trim($message) === '') {
            return null;
        }

        return XpertaExternalMessageSanitizer::sanitize($message);
    }

    private function safeResponseCode(array $response): string|int|null
    {
        $code = data_get($response, 'code', data_get($response, 'status'));

        if (is_int($code)) {
            return $code;
        }

        if (
            is_string($code)
            && preg_match('/^[A-Za-z0-9_.-]{1,50}$/', $code)
        ) {
            return $code;
        }

        return null;
    }

    private function normalizePostalCode(string $postalCode): string
    {
        $postalCode = substr(
            preg_replace('/\D/', '', $postalCode),
            0,
            5
        );

        if (strlen($postalCode) !== 5) {
            throw new RuntimeException(
                'El código postal enviado a Xperta no es válido.'
            );
        }

        return $postalCode;
    }

}
