<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/bootstrap.php';
$recipient=$argv[1]??'';
if (!filter_var($recipient,FILTER_VALIDATE_EMAIL)) { fwrite(STDERR,"Usage: php api/mail-test.php recipient@example.com\n"); exit(1); }
if (config('mail.default')!=='smtp') throw new RuntimeException('Configure SMTP before testing real delivery.');
try {
    Illuminate\Support\Facades\Mail::to($recipient)->send(new App\Mail\RegistrationCode(sprintf('%06d',random_int(0,999999))));
    echo "SMTP accepted one verification delivery test. This code does not activate an account.\n";
} catch(Throwable $error) {
    fwrite(STDERR,'Delivery test failed ('.get_class($error).").\n"); exit(1);
}
