<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$recipient = $argv[1] ?? '';
if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('A designated test mailbox is required.');
}
if (config('mail.default') !== 'smtp') {
    throw new RuntimeException('Expected the configured SMTP transport.');
}
config(['mail.mailers.smtp.timeout' => 15]);
$reference = 'PF-AUDIT-'.gmdate('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));
$result = ['attempted_at' => gmdate('c'), 'reference' => $reference, 'subject' => 'PocketFinds external email test '.$reference,
    'recipient_sha256' => hash('sha256', strtolower($recipient)), 'smtp_accepted' => false, 'delivery_verified' => false];
try {
    $sent = Mail::raw("This is your authorized PocketFinds external email delivery test.\n\nVerification reference: {$reference}\n\nPlease confirm whether this message arrived in your inbox or spam folder. This message contains no account password or verification code.",
        fn ($message) => $message->to($recipient)->subject($result['subject']));
    $result['smtp_accepted'] = $sent !== null;
    $result['message_id'] = $sent?->getMessageId();
    $result['needed'] = 'Recipient confirmation with matching reference; SMTP acceptance alone does not prove inbox delivery.';
} catch (Throwable $error) {
    $result['error_class'] = get_class($error);
    $result['error_code'] = $error->getCode();
    $result['needed'] = 'Resolve SMTP transport/authentication or network access failure, then repeat the designated-mailbox test.';
    // Keep detailed transport errors out of the shareable report.
    $directory = storage_path('app/private/teacher-audit');
    if (! is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    file_put_contents($directory.'/external-mail-error.txt', (string) $error);
}
file_put_contents(dirname(__DIR__, 2).'/docs/teacher-test-audit/external-mail-test.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['smtp_accepted'] ? 0 : 1);
