<?php

namespace App\Services\Shipping\Estafeta;

class EstafetaConfigurationValidator
{
    private const ENDPOINTS = ['auth_url'];
    private const CREDENTIALS = ['client_id', 'client_secret', 'scope'];

    public function validate(?array $configuration = null): array
    {
        $config = $configuration ?? (array) config('zigo_estafeta', []);
        $missing = [];
        $warnings = [];
        $environment = strtolower((string) ($config['environment'] ?? ''));
        if (!in_array($environment, ['stage', 'production'], true)) $missing[] = 'environment';
        if (!($config['enabled'] ?? false)) $warnings[] = 'La integración está deshabilitada.';
        if (($config['mock_enabled'] ?? false) && app()->environment('production')) $missing[] = 'mock_disabled_in_production';
        foreach (self::ENDPOINTS as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value === '') $missing[] = $key;
            elseif (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)) $missing[] = $key . '_invalid';
            elseif ($environment === 'production' && parse_url($value, PHP_URL_SCHEME) !== 'https') $missing[] = $key . '_requires_https';
        }
        foreach (self::CREDENTIALS as $key) if (blank($config[$key] ?? null)) $missing[] = $key;
        foreach (['coverage','quote'] as $contract) if (($config['contracts'][$contract] ?? 'unknown') !== 'confirmed') $warnings[] = $contract . '_contract_' . ($config['contracts'][$contract] ?? 'unknown');
        if (($config['timeout'] ?? 0) <= 0 || ($config['connect_timeout'] ?? 0) <= 0) $missing[] = 'timeouts';
        if (($config['verify_ssl'] ?? true) === false) $warnings[] = 'La verificación TLS está deshabilitada.';

        return [
            'valid' => $missing === [], 'missing' => array_values(array_unique($missing)), 'warnings' => $warnings,
            'environment' => $environment,
            'endpoints' => array_combine(self::ENDPOINTS, array_map(fn ($k) => filled($config[$k] ?? null), self::ENDPOINTS)),
            'credentials_present' => array_combine(self::CREDENTIALS, array_map(fn ($k) => filled($config[$k] ?? null), self::CREDENTIALS)),
        ];
    }
}
