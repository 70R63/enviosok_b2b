<?php
return [
    'reminder_days' => array_values(array_filter(array_map('intval', explode(',', env('ZIGO_BILLING_REMINDER_DAYS', '30,15,7,3,1,0'))), fn(int $day) => $day >= 0)),
    'suspend_after_days' => max(0, (int) env('ZIGO_BILLING_SUSPEND_AFTER_DAYS', 0)),
];
