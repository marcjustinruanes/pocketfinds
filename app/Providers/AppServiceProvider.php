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
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($user, string $token) {
            $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Reset your PocketFinds password')
                ->action('Reset password', $resetUrl)
                ->view('emails.password-reset', [
                    'resetUrl' => $resetUrl,
                    'expiresIn' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });

        Event::listen(TransactionCommitted::class,
            fn ($event) => app(MediaLifecycle::class)->committed($event->connection));
        Event::listen(TransactionRolledBack::class,
            fn ($event) => app(MediaLifecycle::class)->rolledBack($event->connection));
    }
}
