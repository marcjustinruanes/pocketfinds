<?php
declare(strict_types=1);
putenv('POCKETFINDS_BUYER_RUNTIME=1');
require dirname(__DIR__).'/bootstrap.php';
$source=(new ReflectionClass(App\Services\RiderDeliveryOffers::class))->getFileName();
if(realpath($source)!==realpath(dirname(__DIR__,3).'/app/Services/RiderDeliveryOffers.php')) throw new RuntimeException('Mobile is not using the canonical assignment service.');
if((new ReflectionClass(App\Services\RiderDeliveryOffers::class))->hasConstant('OFFER_SECONDS')) throw new RuntimeException('Timed assignment service loaded.');
echo 'PASS Mobile loads the shared municipality-based assignment service without a deadline.'.PHP_EOL;
