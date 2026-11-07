<?php

return [
    // Enrollment only. Existing obligations continue to be serviced when disabled.
    'enabled' => env('COMMISSION_ENABLED', false),
    'activation_at' => env('COMMISSION_ACTIVATION_AT'),
    'due_days' => 7,
];
