<?php

return [
    'unverified_guide_limit' => (int) env(
        'B2C_UNVERIFIED_GUIDE_LIMIT',
        2
    ),

    'payment_reservation_hours' => (int) env(
        'B2C_IDENTITY_PAYMENT_RESERVATION_HOURS',
        24
    ),
];
