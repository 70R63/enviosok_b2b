<?php

$csv = static fn (string $key): array => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env($key, ''))
)));

return [
    'trusted_hosts' => $csv('ZIGO_TRUSTED_HOSTS'),
    'trusted_base_domains' => $csv('ZIGO_TRUSTED_BASE_DOMAINS'),
    'csp_report_only' => (bool) env('ZIGO_CSP_REPORT_ONLY', true),
    'csp' => env('ZIGO_CSP', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self' https://www.mercadopago.com https://*.mercadopago.com; img-src 'self' data: blob: https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' https:; worker-src 'self' blob:; manifest-src 'self'"),
    'evidence_orphan_hours' => (int) env('ZIGO_EVIDENCE_ORPHAN_HOURS', 48),
];
