<?php

return [
    'enabled' => env('AI_ENABLED', false),
    'default_provider' => env('AI_DEFAULT_PROVIDER'),
    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', 'database'),
        'name' => env('AI_QUEUE_NAME', 'ai'),
    ],
    'entitlement' => ['module_code' => 'AI_CORE'],
    'knowledge' => ['disk' => env('AI_KNOWLEDGE_DISK', 'local')],
    'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 30),
    'max_attempts' => (int) env('AI_MAX_ATTEMPTS', 3),
    'job_timeout' => env('AI_JOB_TIMEOUT', 120),
    'job_backoff_seconds' => env('AI_JOB_BACKOFF_SECONDS', '10,30,60'),
    'data_retention_days' => (int) env('AI_DATA_RETENTION_DAYS', 90),
];
