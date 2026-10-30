<?php

return [
    'reminder_offsets' => [7, 3, 1],
    'max_attempts' => 3,
    'backoff_seconds' => [1, 2],
    // Demo-only fault injection: the FIRST email attempt to this address times out.
    'simulate_transient_failure_email' => env('ALERT_SIMULATE_TRANSIENT_EMAIL', ''),
];
