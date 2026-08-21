<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\SellerController;

Route::get('/', fn() => view('guest.home'));
Route::get('/product/{id}', [GuestController::class, 'product'])->name('guest.product');
Route::get('/shop/{slug}', [GuestController::class, 'shop'])->name('guest.shop');

Route::middleware('web')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::post('/login', [AdminController::class, 'loginPost'])->name('login.post');
    Route::get('/register/type', fn() => view('auth.account-type'))->name('register.type');
    Route::get('/register/method', fn() => view('auth.signup-method'))->name('register.method');
    Route::get('/register/google', fn() => view('auth.register-google'))->name('register.google');
    Route::get('/register', fn() => view('auth.register'))->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    Route::get('/register/categories', [RegisterController::class, 'categories'])->name('register.categories');
    Route::post('/register/send-otp', [RegisterController::class, 'sendOtp'])->name('register.send-otp');
    Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])->name('register.verify-otp');
    Route::get('/register/check-username', [RegisterController::class, 'checkUsername'])->name('register.check-username');
    Route::get('/register/check-business-name', [RegisterController::class, 'checkBusinessName'])->name('register.check-business-name');
    Route::get('/password/reset', fn() => view('auth.login'))->name('password.request');

    Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/auth/google/login', [SocialAuthController::class, 'redirectToGoogleLogin'])->name('google.login');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

// Buyer routes
Route::prefix('buyer')->name('buyer.')->middleware(['web', 'buyer'])->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
    Route::get('/browse', [BuyerController::class, 'browse'])->name('browse');
    Route::get('/product/{id}', [BuyerController::class, 'product'])->name('product');
    Route::get('/shop/{slug}', [BuyerController::class, 'shop'])->name('shop');
    Route::get('/cart', [BuyerController::class, 'cart'])->name('cart');
    Route::post('/cart/add', [BuyerController::class, 'cartAdd'])->name('cart.add');
    Route::patch('/cart/{key}', [BuyerController::class, 'cartUpdate'])->name('cart.update');
    Route::delete('/cart/{key}', [BuyerController::class, 'cartRemove'])->name('cart.remove');
    Route::get('/orders', [BuyerController::class, 'orders'])->name('orders');
    Route::get('/messages', [BuyerController::class, 'messages'])->name('messages');
    Route::get('/account', [BuyerController::class, 'account'])->name('account');
    Route::post('/logout', [BuyerController::class, 'logout'])->name('logout');
});

// Seller routes
Route::prefix('seller')->name('seller.')->middleware(['web', 'seller'])->group(function () {
    Route::get('/dashboard',     [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders',        [SellerController::class, 'orders'])->name('orders');
    Route::get('/inventory',     [SellerController::class, 'inventory'])->name('inventory');
    Route::get('/notifications', [SellerController::class, 'notifications'])->name('notifications');
    Route::get('/prepare',       [SellerController::class, 'prepare'])->name('prepare');
    Route::get('/shipments',     [SellerController::class, 'shipments'])->name('shipments');
    Route::get('/deliveries',    [SellerController::class, 'deliveries'])->name('deliveries');
    Route::get('/feedback',      [SellerController::class, 'feedback'])->name('feedback');
    Route::get('/reports',       [SellerController::class, 'reports'])->name('reports');
    Route::get('/messages',      [SellerController::class, 'messages'])->name('messages');
    Route::post('/notifications/read', [SellerController::class, 'markNotifRead'])->name('notifications.read');
    Route::get('/account',       [SellerController::class, 'account'])->name('account');
    Route::post('/account/profile',  [SellerController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/address',  [SellerController::class, 'updateAddress'])->name('account.address');
    Route::post('/account/shop',     [SellerController::class, 'updateShop'])->name('account.shop');
    Route::post('/account/documents', [SellerController::class, 'updateDocuments'])->name('account.documents');
    Route::post('/account/password', [SellerController::class, 'updatePassword'])->name('account.password');
    Route::post('/logout',       [SellerController::class, 'logout'])->name('logout');
});

// Admin routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('admin')->group(function () {
        Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/registrations', [AdminController::class, 'registrations'])->name('registrations');
        Route::patch('/registrations/{user}/approve', [AdminController::class, 'approveUser'])->name('registrations.approve');
        Route::patch('/registrations/{user}/reject', [AdminController::class, 'rejectUser'])->name('registrations.reject');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::patch('/users/{user}/approve', [AdminController::class, 'activateUser'])->name('users.approve');
        Route::patch('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->name('users.suspend');
        Route::get('/compliance', [AdminController::class, 'compliance'])->name('compliance');
        Route::get('/complaints', [AdminController::class, 'complaints'])->name('complaints');
        Route::patch('/complaints/{id}/resolve', [AdminController::class, 'resolveComplaint'])->name('complaints.resolve');
        Route::get('/commission', [AdminController::class, 'commission'])->name('commission');
        Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
        Route::get('/account', [AdminController::class, 'account'])->name('account');
        Route::post('/account/update', [AdminController::class, 'accountUpdate'])->name('account.update');
        Route::post('/account/password', [AdminController::class, 'passwordUpdate'])->name('account.password');
        Route::get('/doc-requests', [AdminController::class, 'docRequests'])->name('doc-requests');
        Route::patch('/doc-requests/{id}/approve', [AdminController::class, 'approveDocRequest'])->name('doc-requests.approve');
        Route::patch('/doc-requests/{id}/reject', [AdminController::class, 'rejectDocRequest'])->name('doc-requests.reject');
    });
});
