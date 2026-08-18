<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;

Route::get('/', fn() => view('guest.home'));

Route::middleware('web')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::get('/register/type', fn() => view('auth.account-type'))->name('register.type');
    Route::get('/register/method', fn() => view('auth.signup-method'))->name('register.method');
    Route::get('/register/google', fn() => view('auth.register-google'))->name('register.google');
    Route::get('/register', fn() => view('auth.register'))->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/password/reset', fn() => view('auth.login'))->name('password.request');

    Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('google.callback');
});
