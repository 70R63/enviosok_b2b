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
