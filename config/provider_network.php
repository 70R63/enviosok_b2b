<?php

return [
    // Technical consumer on the historical B2C host; UUID wins, missing UUID falls back to slug.
    'corporate_consumer_slug' => env('ZIGO_PROVIDER_NETWORK_CONSUMER_SLUG'),
    'corporate_consumer_uuid' => env('ZIGO_PROVIDER_NETWORK_CONSUMER_UUID'),

    // Explicit publication consent, keyed by provider tenant UUID. Empty means private.
    // Publication never grants eligibility, changes price or affects ranking.
    'public_partner_uuids' => [],
];
