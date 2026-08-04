<?php

return [
    'xperta_stage' => [
        'enabled' => filter_var(env('ZIGO_DEVOPS_XPERTA_STAGE_TESTER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url' => env('ZIGO_DEVOPS_XPERTA_STAGE_BASE_URL'),
        'corporativo' => env('ZIGO_DEVOPS_XPERTA_STAGE_CORPORATIVO'),
        'ltd' => env('ZIGO_DEVOPS_XPERTA_STAGE_LTD', 'estafeta'),
        'email' => env('ZIGO_DEVOPS_XPERTA_STAGE_EMAIL'),
        'password' => env('ZIGO_DEVOPS_XPERTA_STAGE_PASSWORD'),
        'api_key' => env('ZIGO_DEVOPS_XPERTA_STAGE_API_KEY'),
        'token_minutes' => (int) env('ZIGO_DEVOPS_XPERTA_STAGE_TOKEN_MINUTES', 720),
        'connect_timeout' => (int) env('ZIGO_DEVOPS_XPERTA_STAGE_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('ZIGO_DEVOPS_XPERTA_STAGE_TIMEOUT', 20),
        'token_path' => env('ZIGO_DEVOPS_XPERTA_STAGE_TOKEN_PATH', '/api/v1/{corporativo}/login'),
        'frequency_path' => env('ZIGO_DEVOPS_XPERTA_STAGE_FREQUENCY_PATH', '/api/v1/empresas/{corporativo}/ltds/{ltd}/frecuencia/{origin}/{destination}'),
        'quote_path' => env('ZIGO_DEVOPS_XPERTA_STAGE_QUOTE_PATH', '/api/v1/empresas/{corporativo}/ltds/{ltd}/servicios/{service}/cotizaciones'),
        'services' => array_values(array_filter(array_map('trim', explode(',', env('ZIGO_DEVOPS_XPERTA_STAGE_SERVICES', 'terrestre,diasig'))))),
    ],
];
