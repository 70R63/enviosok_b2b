<?php

return [
    'enabled' => filter_var(env('ZIGO_DEVOPS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'max_package_mb' => (int) env('ZIGO_DEVOPS_MAX_PACKAGE_MB', 25),
    'allow_production' => filter_var(env('ZIGO_DEVOPS_ALLOW_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN),
    'php_binary' => env('ZIGO_DEVOPS_PHP_BINARY', PHP_BINARY),
    'paths' => [
        'stage' => env('ZIGO_DEVOPS_STAGE_PATH'),
        'production' => env('ZIGO_DEVOPS_PRODUCTION_PATH'),
    ],
    'allowed_seeders' => [
        'ZigoPublicChannelsSeeder',
        'ZigoUatCommercialConceptRulesSeeder',
    ],
    'allow_uat_commercial_concept_rules_in_production' => filter_var(
        env('ZIGO_ALLOW_UAT_COMMERCIAL_CONCEPT_RULES_IN_PRODUCTION', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
