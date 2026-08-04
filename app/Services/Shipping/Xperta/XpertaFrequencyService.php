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
                'zone_code' => null,
                'periodicity_name' => null,
                'operating_days' => [],
                'is_reexpedition' => null,
                'is_ocurre' => null,
                'restriction' => null,
                'restriction_description' => null,
                'services' => [],
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
                '/api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origin}/{destination}'
            ),
            [
                'corporativo' => config('services.xperta.corporativo'),
                'empresa' => config('services.xperta.corporativo'),
                'ltd' => config('services.xperta.ltd', 'estafeta'),
                'origin' => $originPostalCode,
                'destination' => $destinationPostalCode,
            ]
        );

        $response = $this->client->send(
            (string) config(
                'services.xperta.frequency_method',
                'POST'
            ),
            $path,
            [
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

        return $this->normalizeResponse($response) + [
            'available' => true,
            'source' => 'xperta_frequency',
        ];
    }

    private function normalizeResponse(array $response): array
    {
        $destination = data_get($response, 'data.destinations.0');

        if (!is_array($destination)) {
            throw new RuntimeException(
                'Xperta no devolvió data.destinations[0] en la frecuencia.'
            );
        }

        $required = [
            'zoneCode', 'periodicityName', 'isMonday', 'isTuesday',
            'isWednesday', 'isThursday', 'isFriday', 'isSaturday',
            'isSunday', 'isReexpedition', 'isOcurre', 'restriction',
            'restrictionDescription', 'service',
        ];

        foreach ($required as $field) {
            if (!array_key_exists($field, $destination)) {
                throw new RuntimeException(
                    "Xperta no devolvió {$field} en data.destinations[0]."
                );
            }
        }

        if (!is_array($destination['service'])) {
            throw new RuntimeException(
                'Xperta no devolvió una lista service válida en la frecuencia.'
            );
        }

        $services = [];
        foreach ($destination['service'] as $service) {
            if (!is_array($service)
                || !array_key_exists('name', $service)
                || !array_key_exists('estimatedDeliveryDate', $service)) {
                throw new RuntimeException(
                    'Xperta devolvió un servicio de frecuencia incompleto.'
                );
            }

            $code = $this->serviceCode((string) $service['name']);
            if (!in_array($code, ['terrestre', 'diasig'], true)) {
                continue;
            }

            $services[$code] = [
                'estimated_delivery_date' =>
                    (string) $service['estimatedDeliveryDate'],
            ];
        }

        if ($services === []) {
            throw new RuntimeException(
                'Xperta no devolvió servicios reconocidos en la frecuencia.'
            );
        }

        return [
            'zone_code' => (string) $destination['zoneCode'],
            'periodicity_name' => (string) $destination['periodicityName'],
            'operating_days' => $this->operatingDays($destination),
            'is_reexpedition' => (bool) $destination['isReexpedition'],
            'is_ocurre' => (bool) $destination['isOcurre'],
            'restriction' => (bool) $destination['restriction'],
            'restriction_description' =>
                (string) $destination['restrictionDescription'],
            'services' => $services,
        ];
    }

    private function serviceCode(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim($name));
        $normalized = strtr(mb_strtolower($name), [
            'á' => 'a', 'é' => 'e', 'í' => 'i',
            'ó' => 'o', 'ú' => 'u', 'ü' => 'u', '.' => '',
        ]);

        return match ($normalized) {
            'terrestre' => 'terrestre',
            'dia sig', 'dia siguiente', 'diasig' => 'diasig',
            default => $normalized,
        };
    }

    private function operatingDays(array $destination): array
    {
        $days = [
            'isMonday' => 'lunes',
            'isTuesday' => 'martes',
            'isWednesday' => 'miércoles',
            'isThursday' => 'jueves',
            'isFriday' => 'viernes',
            'isSaturday' => 'sábado',
            'isSunday' => 'domingo',
        ];

        $operatingDays = [];
        foreach ($days as $field => $label) {
            if ((bool) $destination[$field]) {
                $operatingDays[] = $label;
            }
        }

        return $operatingDays;
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
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
