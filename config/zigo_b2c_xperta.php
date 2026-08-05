<?php
return [
    'full_flow_enabled'=>filter_var(env('ZIGO_B2C_XPERTA_FULL_FLOW_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
    'guide_enabled'=>filter_var(env('ZIGO_B2C_XPERTA_GUIDE_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
    'guide_max_attempts'=>(int)env('ZIGO_B2C_XPERTA_GUIDE_MAX_ATTEMPTS',3),
    'tracking_enabled'=>filter_var(env('ZIGO_B2C_XPERTA_TRACKING_ENABLED',false),FILTER_VALIDATE_BOOLEAN),
    'quote_ttl_minutes'=>(int)env('ZIGO_B2C_XPERTA_QUOTE_TTL_MINUTES',30),
];
