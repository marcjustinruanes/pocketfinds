<?php
// Reuse the isolated website chat tests against the controller the app loads.
$root = dirname(__DIR__, 3);
$loader = require $root.'/vendor/autoload.php';
$loader->addClassMap([
    'App\\Http\\Controllers\\BuyerController' => dirname(__DIR__).'/storage/buyer-website/app/Http/Controllers/BuyerController.php',
]);
