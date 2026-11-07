<?php

return [
    // Legacy pre-install fallback only. Once commission_enrollment_settings exists,
    // its locked DB row controls enrollment; .env and config caching cannot override it.
    // Existing obligations continue to be serviced when disabled.
    'enabled' => env('COMMISSION_ENABLED', false),
    'activation_at' => env('COMMISSION_ACTIVATION_AT'),
    'due_days' => 7,
];
