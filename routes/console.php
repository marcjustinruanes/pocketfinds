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

Artisan::command('documents:privatize {--apply : Copy, verify and remove public originals}', function () {
    $service = app(\App\Services\SensitiveDocuments::class);
    $public = \Illuminate\Support\Facades\Storage::disk('public');
    $private = \Illuminate\Support\Facades\Storage::disk('sensitive_documents');
    $lockPath = storage_path('app/private/sensitive-documents-migration.lock');
    $lock = fopen($lockPath, 'c');
    if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) { $this->error('Another document migration is running.'); return 1; }
    $moved = $missing = $failed = 0;
    try {
        $paths = $service->paths();
        foreach (\App\Services\SensitiveDocuments::DIRECTORIES as $directory) {
            $paths = array_merge($paths, $public->allFiles($directory));
        }
        foreach (array_unique($paths) as $path) {
            if (! \App\Services\SensitiveDocuments::safe($path)) { $failed++; continue; }
            if (! $public->exists($path)) { if (! $private->exists($path)) $missing++; continue; }
            if (! $this->option('apply')) { $moved++; continue; }
            try {
                // Refuse symlinks/out-of-root sources before removing any public copy.
                $resolved = realpath($public->path($path));
                $root = realpath($public->path(''));
                if (! $resolved || ! $root || is_link($public->path($path)) || ! str_starts_with(strtolower($resolved), strtolower(rtrim($root, '\\/').DIRECTORY_SEPARATOR))) throw new \RuntimeException('Unsafe source.');
                $contents = $public->get($path);
                $hash = hash('sha256', $contents);
                if (! $private->exists($path)) $private->put($path, $contents);
                if (! hash_equals($hash, hash('sha256', $private->get($path)))) throw new \RuntimeException('Private copy differs; original retained.');
                if (! $public->delete($path)) throw new \RuntimeException('Public original could not be removed.');
                $moved++;
            } catch (\Throwable $error) {
                $failed++;
                \Illuminate\Support\Facades\Log::warning('Sensitive document migration requires review', ['path_hash' => hash('sha256', $path), 'exception_type' => $error::class]);
            }
        }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
    $this->info(($this->option('apply') ? 'Privatized' : 'Would privatize').": {$moved}; missing in both stores: {$missing}; failures: {$failed}.");
    return $failed || $missing ? 1 : 0;
})->purpose('Safely privatize sensitive documents; dry run unless --apply is supplied');
Artisan::command('commerce:sweep', function () { app(\App\Services\BuyerCommerce::class)->sweep(); $this->info('Flash reminders and return deadlines processed.'); });
Schedule::command('commerce:sweep')->everyMinute()->withoutOverlapping();
