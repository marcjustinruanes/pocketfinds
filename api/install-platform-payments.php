<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
try {
    require __DIR__.'/bootstrap.php';
    $migration = require dirname(__DIR__).'/integration/laravel/database/migrations/2026_11_08_000001_platform_payment_workflow.php';
    $migration->up();
    echo "Platform payment/payout schema is installed. Official GCash settings must be configured before checkout.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"Platform payment schema was not installed. Check the local MySQL service and API configuration.\n");
    exit(1);
}
