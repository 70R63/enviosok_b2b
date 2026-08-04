<?php

namespace App\Services\Shipping\Xperta;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class XpertaTokenService
{
    public function __construct(
        private XpertaApiClient $client
    ) {
    }

    public function encodedToken(bool $refresh = false): string
    {
        $rawToken = $this->token($refresh);
        $encodedToken = base64_encode($rawToken);

        if (base64_decode($encodedToken, true) !== $rawToken) {
            throw new RuntimeException('No fue posible codificar el token de Xperta.');
        }

        return $encodedToken;
    }

    public function token(bool $refresh = false): string
    {
        if ($refresh) {
            Cache::forget($this->cacheKey());
        }

        $minutes = min(
            10080,
            max(
                1,
                (int) config('services.xperta.token_minutes', 1440)
            )
        );

        $cachedToken = Cache::get($this->cacheKey());
        if (!is_string($cachedToken) || trim($cachedToken) === '') {
            $fresh = $this->requestToken($minutes);
            $rawToken = $fresh['token'];
            Cache::put($this->cacheKey(), $rawToken, $this->cacheUntil($fresh['expires_at'], $minutes));
        } else {
            $rawToken = $this->normalizeRawToken($cachedToken);

            // Repair legacy cache entries that accidentally stored Base64.
            if ($rawToken !== $cachedToken) {
                Cache::put($this->cacheKey(), $rawToken, now()->addMinutes(max(1, $minutes - 5)));
            }
        }

        return $rawToken;
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Requests a new token without reading, replacing or invalidating the shared cache.
     * The raw token is returned only to the in-process diagnostic command.
     */
    public function requestFreshForDiagnostic(): array
    {
        $minutes = min(10080, max(1, (int) config('services.xperta.token_minutes', 1440)));
        $path = $this->tokenPath();
        $headers = $this->authenticationHeaders();
        $result = $this->client->sendQueryWithMeta('POST', $path, $this->tokenPayload($minutes), $headers);
        $message = data_get($result, 'json.message');
        $tokenValue = is_array($message) ? ($message['token'] ?? null) : null;

        return [
            'token' => is_string($tokenValue) ? trim($tokenValue) : null,
            'token_key_present' => is_array($message) && array_key_exists('token', $message),
            'response_shape_valid' => data_get($result, 'json.success') === true && is_array($message),
            'http_status' => $result['http_status'],
            'duration_ms' => $result['duration_ms'],
            'correlation_id' => $result['correlation_id'],
            'path' => $path,
            'x_api_key_sent' => trim((string) ($headers['x-api-key'] ?? '')) !== '',
            'corporativo_header_sent' => trim((string) ($headers['Corporativo'] ?? '')) !== '',
            'minutos_header_sent' => trim((string) ($headers['minutos'] ?? '')) !== '',
            'accept_header_sent' => true,
            'login_parameters_location' => 'query',
            'content_type' => null,
            'expires_at' => is_array($message) && is_string($message['expires_at'] ?? null)
                ? $message['expires_at'] : null,
        ];
    }

    private function requestRawToken(int $minutes): string
    {
        $path = $this->tokenPath();

        return $this->requestToken($minutes)['token'];
    }

    private function requestToken(int $minutes): array
    {
        $result = $this->client->sendQueryWithMeta(
            'POST', $this->tokenPath(), $this->tokenPayload($minutes), $this->authenticationHeaders()
        );
        $token = trim((string) data_get($result, 'json.message.token', ''));
        if (data_get($result, 'json.success') !== true) {
            throw new RuntimeException('Xperta devolvió una respuesta de login inválida.');
        }
        if ($token === '') {
            throw new RuntimeException('Xperta no devolvió el token esperado en message.token.');
        }
        $token = $this->normalizeRawToken($token, false);
        return [
            'token' => $token,
            'expires_at' => data_get($result, 'json.message.expires_at'),
        ];
    }

    private function normalizeRawToken(string $token, bool $allowLegacyEncoded = true): string
    {
        $token = trim($token);

        if (str_contains($token, '|')) {
            return $token;
        }

        if ($allowLegacyEncoded) {
            $decoded = base64_decode($token, true);
            if (is_string($decoded) && str_contains($decoded, '|')) {
                return $decoded;
            }
        }

        throw new RuntimeException('El token crudo de Xperta no contiene el separador esperado.');
    }

    private function tokenPath(): string
    {
        return $this->client->resolvePath(
            (string) config(
                'services.xperta.token_path',
                '/api/v1/{corporativo}/login'
            ),
            [
                'corporativo' => config('services.xperta.corporativo'),
                'empresa' => config('services.xperta.corporativo'),
            ]
        );
    }

    private function tokenPayload(int $minutes): array
    {
        return [
            'email' => config('services.xperta.email'),
            'password' => config('services.xperta.password'),
            'minutos' => $minutes,
        ];
    }

    private function authenticationHeaders(): array
    {
        return [
            'x-api-key' => (string) config('services.xperta.api_key'),
            'Corporativo' => (string) config(
                'services.xperta.corporativo'
            ),
            'minutos' => (string) min(10080, max(1, (int) config('services.xperta.token_minutes', 1440))),
            'Accept' => 'application/json',
        ];
    }

    private function cacheUntil($expiresAt, int $fallbackMinutes)
    {
        if (is_string($expiresAt) && trim($expiresAt) !== '') {
            try {
                $expiration = Carbon::parse($expiresAt)->subMinutes(5);
                if ($expiration->isFuture()) return $expiration;
            } catch (Throwable $ignored) {
            }
        }
        return now()->addMinutes(max(1, $fallbackMinutes - 5));
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash(
            'sha256',
            implode('|', [
                (string) config('services.xperta.base_url'),
                (string) config('services.xperta.corporativo'),
                (string) config('services.xperta.email'),
            ])
        );
    }
}
