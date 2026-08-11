<?php

return [
    'subdomain_base' => env('ZIGO_TENANT_DOMAIN', 'zigo-envios.com'),
    'reservation_minutes' => (int) env('ZIGO_ONBOARDING_RESERVATION_MINUTES', 60),
    'tax_rate' => env('ZIGO_ONBOARDING_TAX_RATE', '0.00'),
    'managed_subdomains_are_verified' => filter_var(
        env('ZIGO_ONBOARDING_MANAGED_SUBDOMAINS_VERIFIED', true),
        FILTER_VALIDATE_BOOLEAN
    ),
    'tenant_admin_scheme' => env('ZIGO_ONBOARDING_TENANT_SCHEME', 'https'),
    'reserved_subdomains' => [
        'www', 'api', 'admin', 'network', 'payments', 'payment', 'support',
        'soporte', 'crm', 'driver', 'mail', 'stage', 'staging', 'sandbox',
        'dev', 'test', 'rapidgo', 'zigo',
    ],
];
