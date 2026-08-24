<?php

return [
    'simulator' => [
        'max_scenarios_per_agent' => 50,
        'max_scenarios_per_run' => 50,
        'max_turns_per_scenario' => 10,
        'max_user_message_bytes' => 8000,
        'max_fixture_bytes' => 32768,
        'max_transcript_bytes' => 65536,
    ],
    'enabled' => env('AI_ENABLED', false),
    'default_provider' => env('AI_DEFAULT_PROVIDER'),
    'providers' => [
        'openai' => [
            'endpoint' => 'https://api.openai.com/v1/responses',
            'api_key' => env('AI_OPENAI_API_KEY'),
            'project' => env('AI_OPENAI_PROJECT'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-5.6-luna'),
            'approved_models' => ['gpt-5.6-luna'],
            'reasoning_effort' => 'none',
            'max_output_tokens' => 600,
            'timeout' => 30,
            'pricing' => ['gpt-5.6-luna' => ['input' => 200000, 'cached_input' => 20000, 'output' => 1200000]],
        ],
    ],
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
    'conversation_history_max_messages' => 6,
    'conversation_history_max_characters' => 6000,
    'conversation_stale_turn_seconds' => (int) env('AI_CONVERSATION_STALE_TURN_SECONDS', 120),
    'actions' => [
        'max_input_bytes' => (int) env('AI_ACTION_MAX_INPUT_BYTES', 16384),
        'max_output_bytes' => (int) env('AI_ACTION_MAX_OUTPUT_BYTES', 32768),
    ],
];
