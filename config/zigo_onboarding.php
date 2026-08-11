<?php

return [
    'subdomain_base' => env('ZIGO_TENANT_DOMAIN', 'zigo-envios.com'),
    'reservation_minutes' => (int) env('ZIGO_ONBOARDING_RESERVATION_MINUTES', 60),
    'tax_rate' => env('ZIGO_ONBOARDING_TAX_RATE', '0.00'),
    'reserved_subdomains' => [
        'www', 'api', 'admin', 'network', 'payments', 'payment', 'support',
        'soporte', 'crm', 'driver', 'mail', 'stage', 'staging', 'sandbox',
        'dev', 'test', 'rapidgo', 'zigo',
    ],
];
