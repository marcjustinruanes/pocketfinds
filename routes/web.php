<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\SellerController;
use App\Models\Message;
use App\Models\Complaint;
use Illuminate\Support\Facades\Storage;

Route::get('/', [GuestController::class, 'home']);
Route::get('/product/{id}', [GuestController::class, 'product'])->name('guest.product');
Route::get('/shop/{slug}', [GuestController::class, 'shop'])->name('guest.shop');

Route::middleware('web', 'auth')->get('/message-media/{path}', function (string $path) {
    $message = Message::where('attachment_path', $path)
        ->where(function ($query) {
            $query->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id());
        })->firstOrFail();

    abort_unless(Storage::disk('public')->exists($message->attachment_path), 404);
    return response()->file(Storage::disk('public')->path($message->attachment_path));
})->where('path', '.*')->name('message.media');

Route::middleware('web', 'auth')->get('/report-evidence/{path}', function (string $path) {
    $complaint = Complaint::where('evidence_path', $path)->firstOrFail();
    abort_unless(auth()->user()->is_admin || auth()->id() === $complaint->complainant_id || auth()->id() === $complaint->respondent_id, 403);
    abort_unless(Storage::disk('public')->exists($complaint->evidence_path), 404);
    return response()->file(Storage::disk('public')->path($complaint->evidence_path));
})->where('path', '.*')->name('report.evidence');

Route::middleware('web')->group(function () {
    Route::get('/login', fn() => view('auth.login'))->name('login');
    Route::post('/login', [AdminController::class, 'loginPost'])->name('login.post');
    Route::get('/register/type', fn() => view('auth.account-type'))->name('register.type');
    Route::get('/register/method', fn() => redirect()->route('register', ['type' => request('type', 'buyer')]))->name('register.method');
    Route::get('/register/google', fn() => redirect()->route('register', ['type' => request('type', 'buyer'), 'google' => 1]))->name('register.google');
    Route::get('/register', function () {
        if (!request()->boolean('google')) {
            session()->forget(['google_id', 'google_name', 'google_email', 'google_avatar']);
        }
        return view('auth.register');
    })->name('register');
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
    Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout');
    Route::post('/cart/add', [BuyerController::class, 'cartAdd'])->name('cart.add');
    Route::patch('/cart/{key}/edit', [BuyerController::class, 'cartEdit'])->where('key', '.*')->name('cart.edit');
    Route::patch('/cart/{key}', [BuyerController::class, 'cartUpdate'])->where('key', '.*')->name('cart.update');
    Route::delete('/cart/{key}', [BuyerController::class, 'cartRemove'])->where('key', '.*')->name('cart.remove');
    Route::get('/orders', [BuyerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/cancel', [BuyerController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/messages', [BuyerController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [BuyerController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [BuyerController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [BuyerController::class, 'messagesSend'])->name('messages.send');
    Route::get('/account', [BuyerController::class, 'account'])->name('account');
    Route::post('/logout', [BuyerController::class, 'logout'])->name('logout');
});

// Seller routes
Route::prefix('seller')->name('seller.')->middleware(['web', 'seller'])->group(function () {
    Route::get('/dashboard',     [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders',        [SellerController::class, 'orders'])->name('orders');
    Route::get('/inventory',     [SellerController::class, 'inventory'])->name('inventory');
    Route::post('/inventory',     [SellerController::class, 'storeProduct'])->name('inventory.store');
    Route::delete('/inventory/{product}', [SellerController::class, 'destroyProduct'])->name('inventory.destroy');
    Route::get('/notifications', [SellerController::class, 'notifications'])->name('notifications');
    Route::get('/prepare',       [SellerController::class, 'prepare'])->name('prepare');
    Route::get('/shipments',     [SellerController::class, 'shipments'])->name('shipments');
    Route::get('/deliveries',    [SellerController::class, 'deliveries'])->name('deliveries');
    Route::get('/feedback',      [SellerController::class, 'feedback'])->name('feedback');
    Route::get('/reports',       [SellerController::class, 'reports'])->name('reports');
    Route::get('/messages',      [SellerController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [SellerController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [SellerController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [SellerController::class, 'messagesSend'])->name('messages.send');
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
Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
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
    Route::get('/reports/export/sales', [AdminController::class, 'exportSalesReport'])->name('reports.export.sales');
    Route::get('/reports/export/commission', [AdminController::class, 'exportCommissionReport'])->name('reports.export.commission');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings/announcements', [AdminController::class, 'storeAnnouncement'])->name('settings.announcements.store');
    Route::delete('/settings/announcements/{id}', [AdminController::class, 'destroyAnnouncement'])->name('settings.announcements.destroy');
    Route::post('/settings/policies', [AdminController::class, 'storePolicy'])->name('settings.policies.store');
    Route::patch('/settings/policies/{policy}', [AdminController::class, 'updatePolicy'])->name('settings.policies.update');
    Route::delete('/settings/policies/{policy}', [AdminController::class, 'destroyPolicy'])->name('settings.policies.destroy');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/messages/{user}', [AdminController::class, 'messages'])->name('messages.user');
    Route::post('/messages/{user}', [AdminController::class, 'sendMessage'])->name('messages.send');
    Route::get('/account', [AdminController::class, 'account'])->name('account');
    Route::post('/account/update', [AdminController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/password', [AdminController::class, 'passwordUpdate'])->name('account.password');
    Route::get('/doc-requests', [AdminController::class, 'docRequests'])->name('doc-requests');
    Route::patch('/doc-requests/{id}/approve', [AdminController::class, 'approveDocRequest'])->name('doc-requests.approve');
    Route::patch('/doc-requests/{id}/reject', [AdminController::class, 'rejectDocRequest'])->name('doc-requests.reject');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::patch('/products/{id}/approve', [AdminController::class, 'approveProduct'])->name('products.approve');
    Route::patch('/products/{id}/reject', [AdminController::class, 'rejectProduct'])->name('products.reject');
});

// Logistics routes
Route::prefix('logistics')->name('logistics.')->middleware('logistics')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/requests', [LogisticsController::class, 'requests'])->name('requests');
    Route::patch('/requests/{id}/approve', [LogisticsController::class, 'approveRequest'])->name('requests.approve');
    Route::patch('/requests/{id}/reject', [LogisticsController::class, 'rejectRequest'])->name('requests.reject');
    Route::get('/assignments', [LogisticsController::class, 'assignments'])->name('assignments');
    Route::get('/monitor', [LogisticsController::class, 'monitor'])->name('monitor');
    Route::patch('/status/{id}', [LogisticsController::class, 'updateStatus'])->name('status.update');
    Route::get('/issues', [LogisticsController::class, 'issues'])->name('issues');
    Route::get('/history', [LogisticsController::class, 'history'])->name('history');
    Route::get('/reports', [LogisticsController::class, 'reports'])->name('reports');
    Route::get('/notifications', [LogisticsController::class, 'notifications'])->name('notifications');
    Route::get('/messages', [LogisticsController::class, 'messages'])->name('messages');
    Route::get('/messages/{userId}', [LogisticsController::class, 'messagesThread'])->name('messages.thread');
    Route::post('/messages/{userId}', [LogisticsController::class, 'messagesSend'])->name('messages.send');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::post('/account/update', [LogisticsController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/password', [LogisticsController::class, 'passwordUpdate'])->name('account.password');
});
