<?php

return [
    // Keep disabled until the account owner supplies the official QR and account.
    'enabled' => env('POCKETFINDS_GCASH_ENABLED', false),
    'account_name' => env('POCKETFINDS_GCASH_ACCOUNT_NAME'),
    'account_number' => env('POCKETFINDS_GCASH_ACCOUNT_NUMBER'),
    'qr_path' => env('POCKETFINDS_GCASH_QR_PATH'), // local disk, not a client URL
    'verification_hours' => (int) env('POCKETFINDS_GCASH_VERIFICATION_HOURS', 24),
    'payment_hours' => (int) env('POCKETFINDS_GCASH_PAYMENT_HOURS', 1),
    'support_email' => env('POCKETFINDS_SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
];
