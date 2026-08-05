<?php

namespace App\Console\Commands;

use App\Models\ZigoProviderApiEvent;
use App\Services\Shipping\Xperta\XpertaProviderException;
use App\Services\Shipping\Xperta\XpertaTokenService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class XpertaTokenCheckCommand extends Command
{
    protected $signature = 'zigo:xperta-token-check
        {--environment=production : Ambiente objetivo; la ejecución activa solo admite production}
        {--confirm= : Confirmación requerida para PRD}
        {--execute : Ejecuta una única solicitud nueva de login; sin esta opción es dry-run}';

    protected $description = 'Valida aisladamente el login Xperta en PRD, sin usar caché ni ejecutar operaciones comerciales.';

    public function handle(XpertaTokenService $tokens): int
    {
        $environment = strtolower(trim((string) $this->option('environment')));
        $execute = (bool) $this->option('execute');

        if ($environment !== 'production') {
            return $this->blocked('XPERTA_TOKEN_ENVIRONMENT_NOT_ALLOWED');
        }

        if ($execute && !config('services.xperta.prd_token_check_enabled', false)) {
            return $this->blocked('XPERTA_TOKEN_CHECK_DISABLED');
        }

        if ($execute && !hash_equals('XPERTA-TOKEN-PRD', (string) $this->option('confirm'))) {
            return $this->blocked('XPERTA_TOKEN_CONFIRMATION_INVALID');
        }

        $report = $this->baseReport($environment);
        if (!$execute) {
            $path = $this->store($report);
            $this->line('DRY_RUN=true');
            $this->line('Reporte: ' . $path);
            return self::SUCCESS;
        }

        $startedAt = microtime(true);
        $correlationId = (string) Str::uuid();

        try {
            $result = $tokens->requestFreshForDiagnostic();
            $token = $result['token'] ?? null;
            $report['http_status'] = $result['http_status'] ?? null;
            $report['duration_ms'] = $result['duration_ms'] ?? $this->duration($startedAt);
            $report['x_api_key_sent'] = (bool) ($result['x_api_key_sent'] ?? false);
            $report['api_key_header_sent'] = $report['x_api_key_sent'];
            $report['corporativo_header_sent'] = (bool) ($result['corporativo_header_sent'] ?? false);
            $report['minutos_header_sent'] = (bool) ($result['minutos_header_sent'] ?? false);
            $report['accept_header_sent'] = (bool) ($result['accept_header_sent'] ?? false);
            $report['login_parameters_location'] = $result['login_parameters_location'] ?? 'query';
            $report['content_type'] = $result['content_type'] ?? null;
            $correlationId = $result['correlation_id'] ?: $correlationId;

            if (!($result['response_shape_valid'] ?? false)) {
                $report['error_code'] = 'XPERTA_TOKEN_INVALID_RESPONSE';
            } elseif (!($result['token_key_present'] ?? false) || $token === '') {
                $report['error_code'] = 'XPERTA_TOKEN_MISSING';
            } elseif (!is_string($token)) {
                $report['error_code'] = 'XPERTA_TOKEN_INVALID_RESPONSE';
            } else {
                $report['success'] = true;
                $report['token_received'] = true;
                $report['token_length'] = strlen($token);
                $report['token_fingerprint'] = substr(hash('sha256', $token), 0, 12);
            }

            unset($token, $result['token']);
        } catch (Throwable $exception) {
            [$errorCode, $httpStatus, $messageCode, $diagnostic] = $this->classify($exception);
            $report['error_code'] = $errorCode;
            $report['http_status'] = $httpStatus;
            $report['provider_message_code'] = $messageCode;
            $report['duration_ms'] = $this->duration($startedAt);
            $report['x_api_key_sent'] = filled(config('services.xperta.api_key'));
            $report['api_key_header_sent'] = $report['x_api_key_sent'];
            $report['corporativo_header_sent'] = filled(config('services.xperta.corporativo'));
            $report['minutos_header_sent'] = true;
            $report['accept_header_sent'] = true;
            $correlationId = $diagnostic['correlation_id'] ?? $correlationId;
        }

        $path = $this->store($report);
        $this->recordEvent($report, $correlationId);
        $this->line('success=' . ($report['success'] ? 'true' : 'false'));
        $this->line('token_received=' . ($report['token_received'] ? 'true' : 'false'));
        $this->line('x_api_key_sent=' . ($report['x_api_key_sent'] ? 'true' : 'false'));
        $this->line('error_code=' . ($report['error_code'] ?? 'none'));
        $this->line('Reporte: ' . $path);

        return $report['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function baseReport(string $environment): array
    {
        $baseUrl = (string) config('services.xperta.base_url');
        $template = (string) config('services.xperta.token_path', '/api/v1/{corporativo}/login');
        $corporativo = (string) config('services.xperta.corporativo');
        $path = str_replace(['{corporativo}', '{empresa}'], rawurlencode($corporativo), $template);
        $resolved = filter_var($path, FILTER_VALIDATE_URL) ? $path : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        return [
            'environment' => $environment,
            'host' => (string) parse_url($resolved, PHP_URL_HOST),
            'path' => (string) parse_url($resolved, PHP_URL_PATH),
            'corporativo' => $corporativo,
            'http_status' => null,
            'success' => false,
            'token_received' => false,
            'token_length' => null,
            'token_fingerprint' => null,
            'x_api_key_sent' => false,
            'api_key_header_sent' => false,
            'corporativo_header_sent' => false,
            'minutos_header_sent' => false,
            'login_parameters_location' => 'query',
            'accept_header_sent' => false,
            'content_type' => null,
            'duration_ms' => 0,
            'provider_message_code' => null,
            'error_code' => null,
        ];
    }

    private function classify(Throwable $exception): array
    {
        if ($exception instanceof XpertaProviderException) {
            $metadata = $exception->diagnosticMetadata;
            $status = (int) ($metadata['http_status'] ?? 0);
            $code = match ($exception->errorCode) {
                'XPERTA_API_KEY_UNAUTHORIZED' => 'XPERTA_TOKEN_API_KEY_UNAUTHORIZED',
                'XPERTA_CREDENTIALS_UNAUTHORIZED' => 'XPERTA_TOKEN_CREDENTIALS_UNAUTHORIZED',
                default => 'XPERTA_TOKEN_HTTP_' . $status,
            };
            return [$code, $status, $metadata['provider_message_code'] ?? 'http_' . $status, $metadata];
        }

        if ($exception instanceof ConnectionException) {
            return ['XPERTA_TOKEN_NETWORK_ERROR', null, 'network_error', []];
        }

        if (preg_match('/HTTP\s+(401|403)/i', $exception->getMessage(), $match)) {
            $status = (int) $match[1];
            return ['XPERTA_TOKEN_HTTP_' . $status, $status, 'http_' . $status, []];
        }

        if (str_contains($exception->getMessage(), 'respuesta que no es JSON válido')) {
            return ['XPERTA_TOKEN_INVALID_RESPONSE', null, 'invalid_response', []];
        }

        return ['XPERTA_TOKEN_NETWORK_ERROR', null, 'network_error', []];
    }

    private function store(array $report): string
    {
        $path = 'private/xperta-token-checks/' . now()->format('Ymd_His_u') . '_' . Str::lower(Str::random(8)) . '.json';
        Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return storage_path('app/' . $path);
    }

    private function recordEvent(array $report, string $correlationId): void
    {
        try {
            if (!Schema::hasTable('zigo_provider_api_events')) {
                return;
            }
            ZigoProviderApiEvent::create([
                'provider' => 'xperta',
                'operation' => 'token_check',
                'environment' => 'production',
                'correlation_id' => $correlationId,
                'status' => $report['success'] ? 'success' : 'failed',
                'http_status' => $report['http_status'],
                'provider_code' => $report['error_code'],
                'duration_ms' => $report['duration_ms'],
                'retry_count' => 0,
                'requested_by_user_id' => null,
                'metadata' => [
                    'host' => $report['host'],
                    'path' => $report['path'],
                    'corporativo' => $report['corporativo'],
                    'x_api_key_sent' => $report['x_api_key_sent'],
                    'api_key_header_sent' => $report['api_key_header_sent'],
                    'corporativo_header_sent' => $report['corporativo_header_sent'],
                    'minutos_header_sent' => $report['minutos_header_sent'],
                    'login_parameters_location' => $report['login_parameters_location'],
                    'accept_header_sent' => $report['accept_header_sent'],
                    'content_type' => $report['content_type'],
                    'provider_message_code' => $report['provider_message_code'],
                    'token_received' => $report['token_received'],
                ],
            ]);
        } catch (Throwable $ignored) {
        }
    }

    private function duration(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function blocked(string $code): int
    {
        $this->error($code);
        return self::FAILURE;
    }
}
