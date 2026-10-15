<?php

namespace App\Providers;

use App\Services\HubHiring;
use App\Services\MediaLifecycle;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(MediaLifecycle::class);
        $this->app->scoped(HubHiring::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TransactionCommitted::class,
            fn ($event) => app(MediaLifecycle::class)->committed($event->connection));
        Event::listen(TransactionRolledBack::class,
            fn ($event) => app(MediaLifecycle::class)->rolledBack($event->connection));
    }
}
