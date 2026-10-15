<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BuyerMiddleware;
use App\Http\Middleware\LogisticsActivity;
use App\Http\Middleware\LogisticsAdminMiddleware;
use App\Http\Middleware\LogisticsMiddleware;
use App\Http\Middleware\MediaConsistency;
use App\Http\Middleware\RiderMiddleware;
use App\Http\Middleware\SellerMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The site sits behind Cloudflare, so without this Laravel never sees the
        // real client scheme/IP — it thinks every request is plain HTTP, which
        // throws off secure-cookie handling and can desync the session cookie
        // from what the server stored (the actual cause of the 419s).
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', MediaConsistency::class);
        // Keep the GCash number literal: surrounding spaces must be rejected.
        $middleware->trimStrings(except: [fn (Request $request) => $request->is('gcash-purchases/*/instructions')]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'logistics' => LogisticsMiddleware::class,
            'logistics.activity' => LogisticsActivity::class,
            'logistics.admin' => LogisticsAdminMiddleware::class,
            'buyer' => BuyerMiddleware::class,
            'seller' => SellerMiddleware::class,
            'rider' => RiderMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
