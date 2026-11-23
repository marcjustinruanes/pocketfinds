<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Serve the native app API through the same HTTPS origin as the website.
// Dispatch before booting the web kernel: the API owns bearer authentication.
$mobilePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nativeApi = $mobilePath === '/app-api' || str_starts_with($mobilePath ?? '', '/app-api/');
$jsonPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
// Older APKs used the domain root as their API base. Keep their JSON calls
// separate from website forms, while retaining the API's normal auth checks.
$legacyPublicApi = $jsonPost && preg_match(
    '#^/(query$|auth/[a-z-]+$|catalog/[a-z-]+$|rpc/[a-z_-]+$)#', $mobilePath ?? '',
);
$legacyAuthenticatedApi = $jsonPost
    && str_starts_with($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ')
    && preg_match(
        '#^/(buyer-api/|buyer/|buyer-web/|website-buyer/|rider/|payments/|delivery/|pickup/|cart/|market/|aftercare/|gcash/|push/|checkout(?:/|$)|upload$)#',
        $mobilePath ?? '',
    );
$legacyMedia = in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)
    && preg_match('#^/(health$|media/|categories/[0-9]+/image$)#', $mobilePath ?? '');
$mobileWeb = str_starts_with($mobilePath ?? '', '/mobile-buyer/')
    || (isset($_COOKIE['pocketfinds_mobile_web']) && preg_match(
        '#^/(buyer/|locations/|message-media/|report-evidence/|gcash-purchases(?:/|$)|account/|commerce/returns/|delivery-proofs/|private-documents/|support/contact$)#',
        $mobilePath ?? '',
    ));
if ($nativeApi || $mobileWeb || $legacyPublicApi || $legacyAuthenticatedApi || $legacyMedia) {
    if ($nativeApi) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/app-api')) ?: '/';
    }
    // The connector terminates TLS at Cloudflare and forwards locally over HTTP.
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
    }
    putenv('API_PUBLIC_URL=https://pocketfindsph.shop/app-api');
    require __DIR__.'/../pocketfinds-app/api/router.php';
    exit;
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
