<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'shipping' => [
        'provider' => env(
            'ZIGO_SHIPPING_PROVIDER',
            'legacy_estafeta'
        ),
        'contract_recorder_enabled' => env('ZIGO_SHIPPING_CONTRACT_RECORDER_ENABLED', false),
        'contract_recorder_allow_production' => env('ZIGO_SHIPPING_CONTRACT_RECORDER_ALLOW_PRODUCTION', false),
        'unified_quote_enabled' => env('ZIGO_B2C_UNIFIED_QUOTE_ENABLED', false),
        'stage_active_checks' => env('ZIGO_DEVOPS_SHIPPING_STAGE_ACTIVE_CHECKS', false),
        'prd_quote_probe_enabled' => env('ZIGO_SHIPPING_PRD_QUOTE_PROBE_ENABLED', false),
        'prd_quote_probe_timeout' => (int) env('ZIGO_SHIPPING_PRD_QUOTE_PROBE_TIMEOUT', 20),
    ],

    'b2c' => [
        'empresa_id' => env('B2C_EMPRESA_ID'),
        'user_id' => env('B2C_USER_ID'),
        'estafeta_servicio_id' => env(
            'B2C_ESTAFETA_SERVICIO_ID'
        ),
    ],

    'xperta' => [
        'enabled' => env('XPERTA_ENABLED', false),
        'environment' => env('XPERTA_ENVIRONMENT', 'sandbox'),
        'base_url' => env('XPERTA_BASE_URL'),
        'empresa' => env('XPERTA_EMPRESA'),
        'ltd' => env('XPERTA_LTD', 'estafeta'),
        'corporativo' => env('XPERTA_CORPORATIVO'),
        'email' => env('XPERTA_EMAIL'),
        'password' => env('XPERTA_PASSWORD'),
        'api_key' => env('XPERTA_API_KEY'),
        'token_minutes' => env('XPERTA_TOKEN_MINUTES', 1440),
        'connect_timeout' => env('XPERTA_CONNECT_TIMEOUT', 5),
        'timeout' => env('XPERTA_TIMEOUT', 20),

        'token_path' => env(
            'XPERTA_TOKEN_PATH',
            '/api/v1/{empresa}/login'
        ),
        'frequency_path' => env(
            'XPERTA_FREQUENCY_PATH',
            '/api/v1/empresas/{empresa}/ltds/{ltd}/frecuencia/{origin}/{destination}'
        ),
        'quote_path' => env(
            'XPERTA_QUOTE_PATH',
            '/api/v1/empresas/{empresa}/ltds/{ltd}/servicios/{service}/cotizaciones'
        ),
        'guide_path' => env(
            'XPERTA_GUIDE_PATH',
            '/api/v1/empresas/{empresa}/ltds/{ltd}/servicios/{service}/guia'
        ),

        'guide_enabled' => env(
            'XPERTA_GUIDE_ENABLED',
            false
        ),
        'guide_probe_enabled' => env(
            'XPERTA_GUIDE_PROBE_ENABLED',
            false
        ),
        'send_api_key_on_operations' => env(
            'XPERTA_SEND_API_KEY_ON_OPERATIONS',
            true
        ),
        'currency' => env(
            'XPERTA_CURRENCY',
            'NMP'
        ),

        'frequency_method' => env(
            'XPERTA_FREQUENCY_METHOD',
            'GET'
        ),
        'quote_method' => env('XPERTA_QUOTE_METHOD', 'POST'),
        'frequency_empresa_id' => env(
            'XPERTA_FREQUENCY_EMPRESA_ID',
            env('XPERTA_EMPRESA')
        ),
        'frequency_enabled' => env(
            'XPERTA_FREQUENCY_ENABLED',
            false
        ),
        'include_declared_value_in_quote' => env(
            'XPERTA_INCLUDE_DECLARED_VALUE_IN_QUOTE',
            false
        ),
        'fallback_to_legacy' => env(
            'XPERTA_FALLBACK_TO_LEGACY',
            false
        ),
        'services' => array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        env(
                            'XPERTA_SERVICES',
                            'terrestre,diasig'
                        )
                    )
                )
            )
        ),
    ],

    'estafeta' => [
        'guide_generation_stale_minutes' => env(
            'ESTAFETA_GUIDE_GENERATION_STALE_MINUTES',
            5
        ),
    ],

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'api_base_url' => env(
            'MERCADOPAGO_API_BASE_URL',
            'https://api.mercadopago.com'
        ),
        'connect_timeout' => env(
            'MERCADOPAGO_CONNECT_TIMEOUT',
            5
        ),
        'timeout' => env('MERCADOPAGO_TIMEOUT', 15),
        'currency' => env('MERCADOPAGO_CURRENCY', 'MXN'),
    ],

    'zigo_internal_billing' => [
        'enabled' => env(
            'ZIGO_INTERNAL_BILLING_ENABLED',
            false
        ),
        'api_client_id' => env(
            'ZIGO_INTERNAL_BILLING_API_CLIENT_ID'
        ),
        'environment' => env(
            'ZIGO_INTERNAL_BILLING_ENVIRONMENT',
            'sandbox'
        ),
        'shipping_product_service_code' => env(
            'ZIGO_INTERNAL_BILLING_SHIPPING_CODE'
        ),
        'insurance_product_service_code' => env(
            'ZIGO_INTERNAL_BILLING_INSURANCE_CODE'
        ),
        'unit_code' => env(
            'ZIGO_INTERNAL_BILLING_UNIT_CODE'
        ),
        'tax_object' => env(
            'ZIGO_INTERNAL_BILLING_TAX_OBJECT'
        ),
        'payment_forms' => [
            'MERCADO_PAGO' => env(
                'ZIGO_INTERNAL_BILLING_MP_PAYMENT_FORM'
            ),
            'SALDO_PREPAGO' => env(
                'ZIGO_INTERNAL_BILLING_BALANCE_PAYMENT_FORM'
            ),
        ],
    ],

    'zigo_webhooks' => [
        'connect_timeout' => env(
            'ZIGO_WEBHOOK_CONNECT_TIMEOUT',
            5
        ),
        'timeout' => env('ZIGO_WEBHOOK_TIMEOUT', 10),
        'max_response_body' => env(
            'ZIGO_WEBHOOK_MAX_RESPONSE_BODY',
            10000
        ),
        'stale_lock_minutes' => env(
            'ZIGO_WEBHOOK_STALE_LOCK_MINUTES',
            5
        ),
        'allow_private_urls' => env(
            'ZIGO_WEBHOOK_ALLOW_PRIVATE_URLS',
            false
        ),
        'user_agent' => env(
            'ZIGO_WEBHOOK_USER_AGENT',
            'ZIGO-Webhook/1.0'
        ),
    ],

];
