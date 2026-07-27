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
                'source' => 'disabled',
                'response' => [],
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
                '/api/v1/empresas/{empresa}/ltds/{ltd}/frecuencia/{origin}/{destination}'
            ),
            [
                'empresa' => config('services.xperta.empresa'),
                'ltd' => config('services.xperta.ltd', 'estafeta'),
                'origin' => $originPostalCode,
                'destination' => $destinationPostalCode,
            ]
        );

        $response = $this->client->send(
            (string) config(
                'services.xperta.frequency_method',
                'GET'
            ),
            $path,
            [
                'empresa_id' => config(
                    'services.xperta.frequency_empresa_id',
                    config('services.xperta.empresa')
                ),
                'token' => $this->tokenService->encodedToken(),
            ],
            $this->providerHeaders()
        );

        $blockedMessage = $this->findBlockedMessage($response);

        if ($blockedMessage !== null) {
            throw new RuntimeException(
                'Xperta reportó que la ruta no está disponible: '
                . $blockedMessage
            );
        }

        return [
            'available' => true,
            'source' => 'xperta_frequency',
            'response' => $response,
        ];
    }

    private function findBlockedMessage(array $response): ?string
    {
        $descriptions = [];

        array_walk_recursive(
            $response,
            function ($value, $key) use (&$descriptions) {
                if (
                    in_array(
                        strtolower((string) $key),
                        ['description', 'descripcion', 'message', 'mensaje'],
                        true
                    )
                    && is_string($value)
                ) {
                    $descriptions[] = trim($value);
                }
            }
        );

        foreach ($descriptions as $description) {
            $normalized = mb_strtolower($description);

            foreach (
                [
                    'bloqueado',
                    'sin cobertura',
                    'no disponible',
                    'no se encuentra',
                    'no existe cobertura',
                ] as $needle
            ) {
                if (str_contains($normalized, $needle)) {
                    return mb_substr($description, 0, 500);
                }
            }
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

    private function providerHeaders(): array
    {
        return [
            'x-api-key' => (string) config('services.xperta.api_key'),
            'Corporativo' => (string) config(
                'services.xperta.corporativo'
            ),
        ];
    }
}
