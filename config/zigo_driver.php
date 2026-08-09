<?php

return [
    'url' => rtrim((string) env('ZIGO_DRIVER_URL', 'http://driver.zigo.local:8000'), '/'),
    'host' => strtolower((string) (parse_url((string) env('ZIGO_DRIVER_URL', 'http://driver.zigo.local:8000'), PHP_URL_HOST) ?: env('ZIGO_DRIVER_HOST', 'driver.zigo.local'))),
    'context_session_key' => 'zigo_driver.active_profile_uuid',
    'presence_ttl_minutes' => (int) env('ZIGO_DRIVER_PRESENCE_TTL_MINUTES', 5),
    'heartbeat_write_interval_seconds' => (int) env('ZIGO_DRIVER_HEARTBEAT_INTERVAL_SECONDS', 30),
];
