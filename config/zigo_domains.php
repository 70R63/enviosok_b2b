<?php

$normalizeUrl = static function ($value, string $default): string {
    $url = trim((string) ($value ?: $default));

    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = 'https://' . $url;
    }

    return rtrim($url, '/');
};

$portalUrls = [
    'b2c' => $normalizeUrl(
        env('ZIGO_B2C_URL'),
        'https://zigo-envios.com'
    ),
    'b2b' => $normalizeUrl(
        env('ZIGO_B2B_URL'),
        'https://empresas.zigo-envios.com'
    ),
    'crm' => $normalizeUrl(
        env('ZIGO_CRM_URL'),
        'https://crm.zigo-envios.com'
    ),
    'support' => $normalizeUrl(
        env('ZIGO_SUPPORT_URL'),
        'https://soporte.zigo-envios.com'
    ),
    'api' => $normalizeUrl(
        env('ZIGO_API_URL'),
        'https://api.zigo-envios.com'
    ),
    'devops' => $normalizeUrl(
        env('ZIGO_DEVOPS_URL'),
        'https://devops.zigo-envios.com'
    ),
];

$portals = [];

foreach ($portalUrls as $portal => $url) {
    $portals[$portal] = [
        'url' => $url,
        'host' => strtolower((string) parse_url($url, PHP_URL_HOST)),
    ];
}

$portals['b2b']['login_url'] = $portalUrls['b2b'] . '/negocios/login';

$configuredDevOpsHost = trim((string) env('ZIGO_DEVOPS_HOST', ''));

if ($configuredDevOpsHost !== '') {
    $devOpsHost = parse_url(
        str_contains($configuredDevOpsHost, '://')
            ? $configuredDevOpsHost
            : '//' . $configuredDevOpsHost,
        PHP_URL_HOST
    );

    $portals['devops']['host'] = strtolower(
        rtrim((string) $devOpsHost, '.')
    );
}

return [
    'routing_enabled' => filter_var(
        env('ZIGO_SUBDOMAIN_ROUTING_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),
    'portals' => $portals,
];
