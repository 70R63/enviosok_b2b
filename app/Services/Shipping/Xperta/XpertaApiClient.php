<?php

namespace App\Services\Shipping\Xperta;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class XpertaApiClient
{
    public function send(
        string $method,
        string $path,
        array $payload = [],
        array $headers = []
    ): array {
        $this->assertBaseConfiguration();

        $response = Http::acceptJson()
            ->asJson()
            ->connectTimeout(
                max(
                    1,
                    (int) config('services.xperta.connect_timeout', 5)
                )
            )
            ->timeout(
                max(
                    1,
                    (int) config('services.xperta.timeout', 20)
                )
            )
            ->withHeaders($headers)
            ->send(
                strtoupper($method),
                $this->resolveUrl($path),
                ['json' => $payload]
            );

        return $this->decode($response);
    }

    public function resolvePath(
        string $template,
        array $replacements = []
    ): string {
        foreach ($replacements as $key => $value) {
            $template = str_replace(
                '{' . $key . '}',
                rawurlencode((string) $value),
                $template
            );
        }

        if (preg_match('/\{[^}]+\}/', $template)) {
            throw new RuntimeException(
                'La ruta configurada de Xperta contiene parámetros sin resolver.'
            );
        }

        return $template;
    }

    private function resolveUrl(string $path): string
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $baseUrl = rtrim(
            (string) config('services.xperta.base_url'),
            '/'
        );

        $normalizedPath = '/' . ltrim($path, '/');

        if (
            str_ends_with($baseUrl, '/api/v1')
            && str_starts_with($normalizedPath, '/api/v1/')
        ) {
            $normalizedPath = substr(
                $normalizedPath,
                strlen('/api/v1')
            );
        }

        return $baseUrl . $normalizedPath;
    }

    private function decode(Response $response): array
    {
        $json = $response->json();

        if (!$response->successful()) {
            throw new RuntimeException(
                'Xperta respondió HTTP '
                . $response->status()
                . ': '
                . $this->safeMessage($json)
            );
        }

        if (!is_array($json)) {
            throw new RuntimeException(
                'Xperta devolvió una respuesta que no es JSON válido.'
            );
        }

        if (
            array_key_exists('success', $json)
            && $json['success'] !== true
        ) {
            throw new RuntimeException(
                'Xperta rechazó la operación: '
                . $this->safeMessage($json)
            );
        }

        return $json;
    }

    private function safeMessage($json): string
    {
        if (!is_array($json)) {
            return 'respuesta no disponible';
        }

        $candidates = [
            data_get($json, 'data.error'),
            data_get($json, 'error'),
            data_get($json, 'message'),
            data_get($json, 'data.message'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_substr(trim($candidate), 0, 500);
            }
        }

        return 'respuesta de error sin detalle';
    }

    private function assertBaseConfiguration(): void
    {
        $required = [
            'services.xperta.base_url' => 'XPERTA_BASE_URL',
            'services.xperta.empresa' => 'XPERTA_EMPRESA',
            'services.xperta.corporativo' => 'XPERTA_CORPORATIVO',
            'services.xperta.email' => 'XPERTA_EMAIL',
            'services.xperta.password' => 'XPERTA_PASSWORD',
            'services.xperta.api_key' => 'XPERTA_API_KEY',
        ];

        $missing = [];

        foreach ($required as $configKey => $environmentKey) {
            if (!filled(config($configKey))) {
                $missing[] = $environmentKey;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Configuración Xperta incompleta: '
                . implode(', ', $missing)
            );
        }
    }
}
