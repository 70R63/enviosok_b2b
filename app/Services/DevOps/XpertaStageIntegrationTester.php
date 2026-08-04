<?php

namespace App\Services\DevOps;

use App\Models\ZigoProviderApiEvent;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class XpertaStageIntegrationTester
{
    public function __construct(private XpertaStageClient $client) {}

    public function configuration(): array
    {
        $isolated = $this->client->configuration();
        $base = (string) ($isolated['base_url'] ?? '');
        return [
            'environment' => 'stage',
            'host' => (string) parse_url($base, PHP_URL_HOST),
            'corporativo' => (string) ($isolated['corporativo'] ?? ''),
            'ltd' => (string) ($isolated['ltd'] ?? ''),
            'services' => array_values((array) ($isolated['services'] ?? [])),
            'credentials' => [
                'email' => filled($isolated['email'] ?? null),
                'password' => filled($isolated['password'] ?? null),
                'api_key' => filled($isolated['api_key'] ?? null),
            ],
            'enabled' => (bool) ($isolated['enabled'] ?? false),
            'production_blocked' => true,
        ];
    }

    public function token(): array
    {
        $this->assertStage();
        $started = microtime(true);
        try {
            $raw = $this->client->freshToken();
            $token = $raw['token'] ?? null;
            $result = [
                'operation' => 'token', 'success' => is_string($token) && $token !== '',
                'http_status' => $raw['http_status'] ?? null,
                'duration_ms' => $raw['duration_ms'] ?? $this->duration($started),
                'token_received' => is_string($token) && $token !== '',
                'token_length' => is_string($token) ? strlen($token) : null,
                'token_fingerprint' => is_string($token) && $token !== '' ? substr(hash('sha256', $token), 0, 12) : null,
                'code' => is_string($token) && $token !== '' ? null : 'XPERTA_TOKEN_MISSING',
            ];
            unset($token, $raw['token']);
        } catch (Throwable $e) {
            $result = $this->failure('token', $e, $started);
        }
        $this->event($result);
        return $result;
    }

    public function frequency(string $origin, string $destination): array
    {
        $this->assertStage();
        $started = microtime(true);
        try {
            $raw = $this->client->frequency($origin, $destination);
            $data = $raw['data'];
            $result = [
                'operation' => 'frequency', 'success' => true, 'origin' => $origin, 'destination' => $destination,
                'coverage' => data_get($data, 'data.coverage', data_get($data, 'coverage', true)),
                'services' => data_get($data, 'data.services', data_get($data, 'services', [])),
                'zone' => data_get($data, 'data.zone', data_get($data, 'zona')),
                'periodicity' => data_get($data, 'data.periodicity', data_get($data, 'periodicidad')),
                'restrictions' => data_get($data, 'data.restrictions', data_get($data, 'restricciones', [])),
                'http_status' => $raw['http_status'], 'duration_ms' => $raw['duration_ms'], 'code' => null,
            ];
        } catch (Throwable $e) { $result = $this->failure('frequency', $e, $started) + ['origin' => $origin, 'destination' => $destination]; }
        $this->event($result);
        return $result;
    }

    public function quote(array $input): array
    {
        $this->assertStage();
        $started = microtime(true);
        $correlation = (string) Str::uuid();
        try {
            $raw = $this->client->quote($input);
            $data = data_get($raw, 'data.data.0', []);
            $correlation = $raw['correlation_id'] ?: $correlation;
            $result = [
                'operation' => 'quote', 'success' => is_array($data) && isset($data['total']), 'service' => $input['service'],
                'cost' => $data['costo'] ?? null, 'extended_area_cost' => $data['costo_ae'] ?? null,
                'subtotal' => $data['sub_total'] ?? null, 'total' => $data['total'] ?? null,
                'currency' => $data['moneda'] ?? 'MXN',
                'http_status' => $raw['http_status'], 'duration_ms' => $raw['duration_ms'],
                'correlation_id' => $correlation, 'code' => isset($data['total']) ? null : 'XPERTA_QUOTE_INVALID_RESPONSE',
            ];
        } catch (Throwable $e) { $result = $this->failure('quote', $e, $started) + ['service' => $input['service'], 'correlation_id' => $correlation]; }
        $this->event($result);
        return $result;
    }

    private function assertStage(): void
    {
        $this->client->assertReady();
    }
    private function duration(float $started): int { return (int) round((microtime(true) - $started) * 1000); }
    private function failure(string $operation, Throwable $e, float $started): array
    {
        $status = preg_match('/HTTP\s+(\d{3})/i', $e->getMessage(), $m) ? (int) $m[1] : null;
        $code = preg_match('/^(XPERTA_[A-Z0-9_]+)/', $e->getMessage(), $codeMatch) ? $codeMatch[1] : 'XPERTA_NETWORK_ERROR';
        return ['operation' => $operation, 'success' => false, 'http_status' => $status, 'duration_ms' => $this->duration($started), 'code' => $code];
    }
    private function event(array $result): void
    {
        try {
            if (!Schema::hasTable('zigo_provider_api_events')) return;
            $safe = array_intersect_key($result, array_flip(['origin','destination','coverage','services','zone','periodicity','restrictions','service','cost','extended_area_cost','subtotal','total','currency','token_received','token_length','token_fingerprint']));
            ZigoProviderApiEvent::create(['provider'=>'xperta','operation'=>'devops_'.$result['operation'],'environment'=>'stage','correlation_id'=>$result['correlation_id']??(string)Str::uuid(),'status'=>$result['success']?'success':'failed','http_status'=>$result['http_status'],'provider_code'=>$result['code'],'duration_ms'=>$result['duration_ms'],'retry_count'=>0,'requested_by_user_id'=>auth()->id(),'metadata'=>$safe]);
        } catch (Throwable $ignored) {}
    }
}
