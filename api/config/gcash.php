<?php
// Historical app payments only. New buyer checkout uses the website settings
// platform_gcash_name / platform_gcash_number and its original payment UI.


// App-only GCash settings. Fill in the official account, then enable payments.
return [
    'enabled' => true,
    'account_name' => 'Chlouie Cabot',
    'account_number' => '09108118859',
    'qr_disk' => 'app-gcash',
    'qr_path' => 'pocketfinds-qr.png',
    'support_email' => 'anchetanicole1020@gmail.com',
];
