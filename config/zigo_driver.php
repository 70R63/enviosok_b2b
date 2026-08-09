<?php

return [
    'presence_ttl_minutes' => (int) env('ZIGO_DRIVER_PRESENCE_TTL_MINUTES', 5),
    'heartbeat_write_interval_seconds' => (int) env('ZIGO_DRIVER_HEARTBEAT_INTERVAL_SECONDS', 30),
];
