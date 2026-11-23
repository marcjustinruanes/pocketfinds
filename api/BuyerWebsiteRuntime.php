<?php
declare(strict_types=1);

/** Buyer and rider adapters use one pinned website runtime and model/service vocabulary. */
function mobileBuyerWebsite(string $website, Composer\Autoload\ClassLoader $loader): string {
    $path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $embedded = preg_match('#^(buyer-api/|buyer/|mobile-buyer/|css/|js/|vendor/pdfjs/|images/|fonts/|assets/|storage/|locations/|message-media/|report-evidence/|gcash/|gcash-purchases|account/|commerce/returns/|delivery-proofs/|private-documents/)#', $path)
        || $path === 'support/contact' || getenv('POCKETFINDS_BUYER_RUNTIME') === '1';
    $source = __DIR__.'/storage/buyer-website';
    $sharedRider=preg_match('#^(cart/|catalog/|rider/|delivery/|pickup/|rpc/|query$|upload$|media/|auth/)#',$path);
    if($sharedRider && !is_file($source.'/revision.txt'))throw new RuntimeException('Install the shared website runtime before using the mobile API.');
    $embedded=$embedded || $sharedRider;
    if (str_starts_with($path, 'market/')) {
        $payload=json_decode(file_get_contents('php://input'),true);
        $embedded=$embedded || ($payload['website_checkout']??false) === true;
    }
    if (!$embedded || !is_file($source.'/revision.txt')) return $website;
    // Composer's optimized class map takes precedence over PSR-4. Override both.
    $classes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source.'/app', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') continue;
        $relative = substr($file->getPathname(), strlen($source.'/app/'), -4);
        $classes['App\\'.str_replace(['/', '\\'], '\\', $relative)] = $file->getPathname();
    }
    $classes['App\\Services\\RegistrationPostalCode'] = __DIR__.'/RegistrationPostalCode.php';
    foreach (['LogisticsFinance','CodSellerPayouts','CommissionLedger','RiderCompensation'] as $service) {
        $classes['App\\Services\\'.$service] = dirname(__DIR__).'/integration/laravel/app/Services/'.$service.'.php';
    }
    // Both runtimes operate on the same offers. Never let the pinned buyer
    // snapshot reintroduce old deadlines or barangay eligibility rules.
    $classes['App\Services\RiderDeliveryOffers'] = $website.'/app/Services/RiderDeliveryOffers.php';
    $classes['App\Http\Controllers\RiderController'] = $website.'/app/Http/Controllers/RiderController.php';
    $loader->addClassMap($classes);
    $loader->addPsr4('App\\', $source.'/app', true);
    return $source;
}
