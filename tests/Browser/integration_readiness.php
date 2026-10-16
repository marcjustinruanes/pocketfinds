<?php

use App\Models\PaymentMethod;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$smtp = config('mail.mailers.smtp');
$result = [
    'checked_at' => gmdate('c'),
    'external_email' => [
        'mailer' => config('mail.default'),
        'smtp_host' => $smtp['host'] ?? null,
        'smtp_port' => $smtp['port'] ?? null,
        'username_configured' => filled($smtp['username'] ?? null),
        'password_configured' => filled($smtp['password'] ?? null),
        'from_is_placeholder' => str_ends_with(config('mail.from.address', ''), '@example.com'),
        'verified' => false,
        'needed' => 'A configured working mail transport and a user-designated test mailbox with inbox access; local mail-log tests do not establish external delivery.',
    ],
    'payments' => [
        'configured_methods' => PaymentMethod::query()->get(['name', 'type', 'is_active'])->toArray(),
        'external_provider_verified' => false,
        'needed' => 'A specified payment provider, sandbox merchant credentials, supported success/failure/cancel/refund scenarios, and a reachable verified webhook endpoint. Current GCash flow records manual payment proof; it does not establish provider charging or refunds.',
    ],
    'google_sign_in' => [
        'client_id_configured' => filled(config('services.google.client_id')),
        'client_secret_configured' => filled(config('services.google.client_secret')),
        'verified' => false,
        'needed' => 'A designated Google test account and a permitted callback URL for end-to-end sign-in.',
    ],
];
file_put_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/integration-readiness.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
