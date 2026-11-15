<?php
declare(strict_types=1);

// Reuse the website's connection, models, mail and storage configuration.
// No database password or application key is copied into the mobile app.
$website = getenv('POCKETFINDS_WEBSITE_PATH');
if (!$website) {
    // Support both a nested app folder and the original sibling layout.
    $parent = dirname(__DIR__, 2);
    $website = is_file($parent.'/artisan') && is_file($parent.'/vendor/autoload.php')
        ? $parent
        : $parent.'/pocketfinds';
}
if (!is_file($website.'/vendor/autoload.php')) {
    throw new RuntimeException('Set POCKETFINDS_WEBSITE_PATH to the existing Laravel PocketFinds folder.');
}
$loader = require $website.'/vendor/autoload.php';
require_once __DIR__.'/BuyerWebsiteRuntime.php';
$buyerWebsite = mobileBuyerWebsite($website, $loader);
$app = require $buyerWebsite.'/bootstrap/app.php';
if ($buyerWebsite !== $website) {
    $app->useEnvironmentPath($website);
    $app->useStoragePath($website.'/storage');
    $app->instance('path.vendor', $website.'/vendor');
    // Standalone mobile databases use the legacy UTC convention. A verified
    // shared-database marker below opts merged installations into Philippine time.
    $app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
        $app['config']->set('app.timezone', 'UTC');
        $app['config']->set('database.connections.mysql.timezone', null);
        date_default_timezone_set('UTC');
    });
}
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// Keep SMTP connection failures within the mobile client's request timeout.
config(['mail.mailers.smtp.timeout' => 15]);
// The standalone API owns its logs rather than depending on website log permissions.
$mobileLogs = __DIR__.'/storage/logs';
if (!is_dir($mobileLogs)) mkdir($mobileLogs, 0775, true);
config(['logging.channels.single.path' => $mobileLogs.'/laravel.log']);
// Merged web/app dumps use the website's Philippine-time convention. Existing
// standalone app databases retain their original connection/timezone behavior.
if (Illuminate\Support\Facades\Schema::hasTable('integration_shared_database_settings')) {
    $shared=Illuminate\Support\Facades\DB::table('integration_shared_database_settings')->pluck('value','name');
    if (($shared['database_name']??null)==='pocketfinds' && ($shared['timezone']??null)==='Asia/Manila') {
        config(['app.timezone'=>'Asia/Manila','database.connections.mysql.timezone'=>'+08:00']);
        date_default_timezone_set('Asia/Manila');
        Illuminate\Support\Facades\DB::statement("SET time_zone='+08:00'");
    }
}
// App-local shared Laravel services; website source/boot bindings remain unchanged.
require_once dirname(__DIR__).'/integration/laravel/app/Services/PlatformPayments.php';
require_once dirname(__DIR__).'/integration/laravel/app/Services/RiderPayouts.php';
config(['platform_payments' => require dirname(__DIR__).'/integration/laravel/config/platform_payments.php']);
config(['filesystems.disks.app-gcash' => [
    'driver' => 'local', 'root' => __DIR__.'/storage/gcash', 'throw' => false,
]]);
config(['platform_payments' => array_replace(config('platform_payments'), require __DIR__.'/config/gcash.php')]);
App\Models\Order::saving(fn($order) => app(App\Services\PlatformPayments::class)->guardOrder($order));
App\Models\Order::saved(function ($order) {
    if ($order->wasChanged('status') && $order->status === 'cancelled' && $order->commerce_snapshot) {
        $service=__DIR__.'/storage/buyer-website/app/Services/BuyerCommerce.php';
        if (!class_exists(App\Services\BuyerCommerce::class) && is_file($service)) require_once $service;
        if (class_exists(App\Services\BuyerCommerce::class)) app(App\Services\BuyerCommerce::class)->release($order->id);
    }
});
App\Models\Shipment::saving(fn($shipment) => app(App\Services\PlatformPayments::class)->guardShipment($shipment));
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'pocketfinds') {
    throw new RuntimeException('The website must use MySQL database pocketfinds.');
}
// App-scoped adapters; the website's own bootstrap and bindings are untouched.
require_once __DIR__.'/Requests.php';
require_once __DIR__.'/RiderWebModule.php';
require_once __DIR__.'/RiderWebsiteDelivery.php';
require_once __DIR__.'/BuyerPricing.php';
require_once __DIR__.'/NativeBuyerCommerce.php';
foreach (['Market','Aftercare','Cart','Catalog','Push','Rider'] as $module) require_once __DIR__.'/'.$module.'.php';
// GCash uses the website service directly, including its platform-admin review flow.
return $app;
