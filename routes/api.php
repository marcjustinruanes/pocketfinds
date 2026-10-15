<?php

use App\Http\Controllers\MobileScannerController;
use App\Http\Middleware\MobileScannerAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')->name('mobile.')->group(function () {
    Route::post('/login', [MobileScannerController::class, 'login'])->middleware('throttle:5,1,mobile-login:')->name('login');
    Route::post('/logout', [MobileScannerController::class, 'logout'])->middleware(['auth:sanctum', 'throttle:30,1,mobile-logout:'])->name('logout');
    Route::middleware(['auth:sanctum', MobileScannerAccess::class, 'throttle:60,1,mobile-scanner:'])->group(function () {
        Route::get('/me', [MobileScannerController::class, 'me'])->name('me');
        Route::post('/parcels/lookup', [MobileScannerController::class, 'lookup'])->name('lookup');
        Route::post('/scans', [MobileScannerController::class, 'syncScan'])->name('scans');
        Route::post('/operations', [MobileScannerController::class, 'operate'])->name('operations');
        Route::post('/pair/{token}', [MobileScannerController::class, 'pair'])->where('token', '[A-Za-z0-9]{64}')->name('pair');
        Route::get('/pair/{token}/status', [MobileScannerController::class, 'pairStatus'])->where('token', '[A-Za-z0-9]{64}')->name('pair.status');
        Route::post('/pair/{token}/disconnect', [MobileScannerController::class, 'disconnectPair'])->where('token', '[A-Za-z0-9]{64}')->name('pair.disconnect');
        Route::post('/pair/{token}/codes', [MobileScannerController::class, 'relay'])->where('token', '[A-Za-z0-9]{64}')->name('relay');
    });
});
