<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:auto-complete', function () {
    $count = \App\Models\Order::autoCompleteDelivered();
    $this->info("Auto-completed {$count} delivered order(s) the buyer never confirmed.");
})->purpose('Complete delivered orders the buyer has not confirmed within ' . \App\Models\Order::AUTO_COMPLETE_DAYS . ' days');

// Also swept (throttled) whenever a buyer or seller opens their orders page, so this
// still happens on a server that isn't running `php artisan schedule:work`.
Schedule::command('orders:auto-complete')->hourly();
