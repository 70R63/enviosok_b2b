<?php

namespace App\Services\Shipping\Xperta;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class XpertaApiClient
{
    /**
     * Send query parameters with an empty request body.
     *
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @return array{
     *     json: array<mixed>,
     *     http_status: int,
     *     duration_ms: int,
     *     correlation_id: string|null
     * }
     */
    public function sendQueryWithMeta(
        string $method,
        string $path,
        array $query,
        array $headers = []
    ): array {
        $this->assertBaseConfiguration();
        $startedAt = microtime(true);
        $url = $this->resolveUrl($path);
        $response = $this->request($headers)->send(
            strtoupper($method),
            $url,
            ['query' => $query]
        );

        $result = $this->responseWithMeta($response, $url, $startedAt);

        return [
            'json' => $result['data'],
            'http_status' => $result['http_status'],
            'duration_ms' => $result['duration_ms'],
            'correlation_id' => $result['correlation_id'],
        ];
    }

    public function send(
        string $method,
        string $path,
        array $payload = [],
        array $headers = [],
        bool $preserveZeroFraction = false
    ): array {
        return $this->sendWithMeta($method, $path, $payload, $headers, $preserveZeroFraction)['data'];
    }

    public function sendWithMeta(
        string $method,
        string $path,
        array $payload = [],
        array $headers = [],
        bool $preserveZeroFraction = false
    ): array {
        $this->assertBaseConfiguration();
        $startedAt = microtime(true);

        $request = $this->request($headers);

        if ($preserveZeroFraction) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
            );

            $url = $this->resolveUrl($path);
            $response = $request
                ->withBody($json, 'application/json')
                ->send(
                    strtoupper($method),
                    $url
                );
        } else {
            $url = $this->resolveUrl($path);
            $response = $request
                ->asJson()
                ->send(
                    strtoupper($method),
                    $url,
                    ['json' => $payload]
                );
        }

        return $this->responseWithMeta($response, $url, $startedAt);
    }

    private function request(array $headers)
    {
        return Http::acceptJson()
            ->connectTimeout(max(1, (int) config('services.xperta.connect_timeout', 5)))
            ->timeout(max(1, (int) config('services.xperta.timeout', 20)))
            ->withHeaders($headers)
            ->withOptions(['allow_redirects' => false]);
    }

    private function responseWithMeta(Response $response, string $url, float $startedAt): array
    {
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        try {
            $data = $this->decode($response, $url);
        } catch (XpertaProviderException $exception) {
            throw new XpertaProviderException(
                $exception->errorCode,
                $exception->diagnosticMetadata + ['duration_ms' => $durationMs],
                $exception
            );
        }

        return [
            'data' => $data,
            'http_status' => $response->status(),
            'duration_ms' => $durationMs,
            'correlation_id' => $this->correlationId($response),
        ];
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

    private function decode(Response $response, string $url): array
    {
        try {
            $json = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Xperta devolvió una respuesta que no es JSON válido.',
                0,
                $exception
            );
        }

        if (!is_array($json)) {
            throw new RuntimeException(
                'Xperta devolvió una respuesta JSON con una estructura inválida.'
            );
        }

        if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
            $location = trim((string) $response->header('Location'));

            throw new RuntimeException(
                'Xperta respondió con una redirección HTTP '
                . $response->status()
                . '. Configura XPERTA_BASE_URL con la URL final HTTPS'
                . ($location !== ''
                    ? '. Destino reportado: ' . mb_substr($location, 0, 300)
                    : '.')
            );
        }

        if ($response->status() === 403) {
            $functionalMessage = $this->safeMessage($json);
            [$errorCode, $messageCode] = $this->classifyForbidden($functionalMessage);
            throw new XpertaProviderException($errorCode, $this->diagnosticMetadata($url, $response, $messageCode));
        }

        if (!$response->successful()) {
            $status = $response->status();
            throw new XpertaProviderException(
                'XPERTA_HTTP_' . $status,
                $this->diagnosticMetadata($url, $response, 'http_' . $status)
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

    private function classifyForbidden(string $message): array
    {
        $normalized = strtolower(\Illuminate\Support\Str::ascii($message));
        if (str_contains($normalized, 'corporativo') && (str_contains($normalized, 'no autorizado') || str_contains($normalized, 'unauthorized'))) return ['XPERTA_CORPORATE_UNAUTHORIZED', 'corporate_unauthorized'];
        if ((str_contains($normalized, 'api key') || str_contains($normalized, 'apikey') || str_contains($normalized, 'llave')) && (str_contains($normalized, 'no autoriz') || str_contains($normalized, 'invalid'))) return ['XPERTA_API_KEY_UNAUTHORIZED', 'api_key_unauthorized'];
        if ((str_contains($normalized, 'credencial') || str_contains($normalized, 'autenticacion') || str_contains($normalized, 'usuario')) && (str_contains($normalized, 'no autoriz') || str_contains($normalized, 'invalid'))) return ['XPERTA_CREDENTIALS_UNAUTHORIZED', 'credentials_unauthorized'];
        return ['XPERTA_HTTP_403', 'http_403'];
    }

    private function diagnosticMetadata(string $url, Response $response, string $messageCode): array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        preg_match('#/servicios/([^/]+)/#', $path, $service);
        return [
            'host' => (string) parse_url($url, PHP_URL_HOST),
            'path' => $path,
            'corporativo' => (string) config('services.xperta.corporativo'),
            'ltd' => (string) config('services.xperta.ltd'),
            'service' => isset($service[1]) ? rawurldecode($service[1]) : null,
            'http_status' => $response->status(),
            'provider_message_code' => $messageCode,
            'correlation_id' => $this->correlationId($response),
        ];
    }

    private function correlationId(Response $response): ?string
    {
        foreach (['X-Correlation-ID', 'X-Request-ID', 'Request-ID'] as $name) {
            $value = trim((string) $response->header($name));
            if ($value !== '') return mb_substr($value, 0, 100);
        }
        return null;
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
