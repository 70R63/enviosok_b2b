<?php

namespace App\Services\Shipping\Xperta;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

class XpertaTokenService
{
    public function __construct(
        private XpertaApiClient $client
    ) {
    }

    public function encodedToken(bool $refresh = false): string
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

        $cacheMinutes = max(1, $minutes - 5);

        $rawToken = Cache::remember(
            $this->cacheKey(),
            now()->addMinutes($cacheMinutes),
            fn () => $this->requestRawToken($minutes)
        );

        return base64_encode($rawToken);
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function withEncodedToken(callable $operation): mixed
    {
        try {
            return $operation($this->encodedToken());
        } catch (XpertaHttpException $exception) {
            if (!in_array($exception->statusCode(), [401, 403], true)) {
                throw $exception;
            }

            $this->forget();

            return $operation($this->encodedToken(true));
        }
    }

    private function requestRawToken(int $minutes): string
    {
        $path = $this->client->resolvePath(
            (string) config(
                'services.xperta.token_path',
                '/api/v1/{empresa}/login'
            ),
            [
                'empresa' => config('services.xperta.empresa'),
            ]
        );

        $response = $this->client->send(
            'POST',
            $path,
            [
                'email' => config('services.xperta.email'),
                'password' => config('services.xperta.password'),
                'minutos' => $minutes,
            ],
            $this->client->providerHeaders()
        );

        $token = trim(
            (string) data_get($response, 'message.token', '')
        );

        if ($token === '') {
            throw new RuntimeException(
                'Xperta no devolvió el token esperado en message.token.'
            );
        }

        return $token;
    }

    private function cacheKey(): string
    {
        return 'xperta:token:' . hash(
            'sha256',
            implode('|', [
                (string) config('services.xperta.base_url'),
                (string) config('services.xperta.empresa'),
                (string) config('services.xperta.corporativo'),
                (string) config('services.xperta.email'),
            ])
        );
    }
}
