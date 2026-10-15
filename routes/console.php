<?php

use App\Models\Order;
use App\Services\GcashWorkflow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:auto-complete', function () {
    $count = Order::autoCompleteDelivered();
    $this->info("Auto-completed {$count} delivered order(s) the buyer never confirmed.");
})->purpose('Complete delivered orders the buyer has not confirmed within '.Order::AUTO_COMPLETE_DAYS.' days');

// Also swept (throttled) whenever a buyer or seller opens their orders page, so this
// still happens on a server that isn't running `php artisan schedule:work`.
Schedule::command('orders:auto-complete')->hourly();

Artisan::command('gcash:expire', function () {
    $this->info(app(GcashWorkflow::class)->sweep().' GCash reservations expired.');
})->purpose('Release expired GCash stock and voucher allocations exactly once');
Schedule::command('gcash:expire')->everyMinute()->withoutOverlapping();
