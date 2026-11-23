<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/bootstrap.php';
echo 'Database: '.config('database.default').' / '.config('database.connections.mysql.database').PHP_EOL;
echo 'Mail transport: '.config('mail.default').' / '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port').PHP_EOL;
echo 'SMTP credentials: '.(config('mail.mailers.smtp.username') && config('mail.mailers.smtp.password') ? 'configured':'missing').PHP_EOL;
echo 'Google web client: '.(config('services.google.client_id') ? 'configured':'missing').PHP_EOL;
if (in_array('--smtp',$argv,true)) {
    try {
        $transport=app('mail.manager')->mailer('smtp')->getSymfonyTransport();
        $transport->start(); $transport->stop();
        echo "SMTP connection and authentication succeeded. No email sent.\n";
    } catch (Throwable $error) {
        echo 'SMTP connection/authentication failed ('.get_class($error).').'.PHP_EOL;
        exit(1);
    }
}
