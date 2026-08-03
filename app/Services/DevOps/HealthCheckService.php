<?php

namespace App\Services\DevOps;

use App\Models\ZigoHealthCheck;
use App\Services\ZigoDomainResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class HealthCheckService
{
    private const CHECKS = [
        'home' => ['/', 'ZIGO'],
        'postal_lookup' => ['/postal-code/lookup/64000', '64000'],
        'colonias' => ['/b2c/cp/colonias?cp=64000', '64000'],
        'crm_login' => ['/crm/login', 'CRM'],
        'negocios_login' => ['/negocios/login', 'ZIGO'],
    ];

    public function __construct(private ZigoDomainResolver $domains) {}

    public function run(string $environment, ?int $userId): Collection
    {
        $baseUrl = $this->baseUrl($environment);

        return collect(self::CHECKS)->map(function (array $definition, string $key) use ($baseUrl, $environment, $userId): ZigoHealthCheck {
            [$path, $expected] = $definition; $started = hrtime(true); $httpStatus = null;
            try {
                $response = Http::acceptJson()->timeout(10)->connectTimeout(5)->withHeaders(['User-Agent' => 'ZIGO-Internal-HealthCheck/1.0'])->get($baseUrl . $path);
                $duration = (int) round((hrtime(true) - $started) / 1000000); $httpStatus = $response->status();
                $hasExpected = str_contains($response->body(), $expected);
                $status = $response->successful() && $hasExpected ? 'success' : ($response->successful() ? 'warning' : 'failed');
                $message = $response->successful() ? ($hasExpected ? 'Respuesta válida y contenido mínimo confirmado.' : 'HTTP válido, pero no se encontró el contenido mínimo esperado.') : 'El endpoint respondió con estado no exitoso.';
            } catch (\Throwable $exception) {
                $duration = (int) round((hrtime(true) - $started) / 1000000); $status = 'failed'; $message = 'No fue posible completar la solicitud de health check.';
            }

            return ZigoHealthCheck::create(['environment' => $environment, 'check_key' => $key, 'status' => $status, 'http_status' => $httpStatus, 'duration_ms' => $duration, 'message' => $message, 'checked_by_user_id' => $userId, 'checked_at' => now()]);
        });
    }

    public function baseUrl(string $environment): string
    {
        if ($environment === 'stage') { $url = (string) config('app.url'); }
        elseif ($environment === 'production') { $url = (string) $this->domains->baseUrl('b2c'); }
        else { throw new \InvalidArgumentException('Ambiente inválido.'); }

        $parts = parse_url($url); $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($parts['scheme'] ?? null, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) { throw new \RuntimeException('La URL base configurada no es segura.'); }
        if ($environment === 'production' && ($parts['scheme'] ?? null) !== 'https') { throw new \RuntimeException('Producción requiere HTTPS.'); }
        return rtrim($url, '/');
    }
}
