<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\RiderController;
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

    // Attachments live in the public 'messages' Supabase bucket — the access
    // check above (must be sender/receiver) is what actually gates this,
    // the redirect just hands off to the CDN once that's confirmed.
    abort_unless(Storage::disk('supabase_messages')->exists($message->attachment_path), 404);
    return redirect(rtrim(config('filesystems.disks.supabase_messages.url'), '/') . '/' . ltrim($message->attachment_path, '/'));
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
    // Rider/Logistics ("delivery team") are internal/operational roles kept off the main
    // customer role picker above — reached only via its own explicit "join the delivery
    // team" link, not listed alongside Buyer/Seller.
    Route::get('/register/delivery-team', fn() => view('auth.delivery-type'))->name('register.delivery-team');
    Route::get('/register/method', [RegisterController::class, 'method'])->name('register.method');

    // Every role (buyer, seller, rider, logistics) gets its own dedicated registration
    // page/view — same google-signup-detection boilerplate for each, factored out here.
    $registerView = function (string $view) {
        $isGoogleSignup = request()->boolean('google') && session()->has('google_email') && session('google_email');
        if (!$isGoogleSignup) {
            session()->forget(['google_id', 'google_name', 'google_email', 'google_avatar']);
        }
        return view($view, [
            'isGoogleSignup' => $isGoogleSignup,
            'googleId'       => $isGoogleSignup ? session('google_id') : null,
            'googleEmail'    => $isGoogleSignup ? session('google_email') : null,
        ]);
    };
    Route::get('/register/buyer', fn () => $registerView('auth.register-buyer'))->name('register.buyer');
    Route::get('/register/seller', fn () => $registerView('auth.register-seller'))->name('register.seller');
    Route::get('/register/rider', fn () => $registerView('auth.register-rider'))->name('register.rider');
    Route::get('/register/logistics', fn () => $registerView('auth.register-logistics'))->name('register.logistics');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    // Legacy shared buyer/seller page — kept so any old bookmark/link still resolves,
    // but nothing in the app links here anymore; use register.buyer/register.seller above.
    Route::get('/register', fn () => $registerView('auth.register'))->name('register');
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
    Route::post('/product/report', [BuyerController::class, 'reportProduct'])->name('product.report');
    Route::get('/shop/{slug}', [BuyerController::class, 'shop'])->name('shop');
    Route::get('/cart', [BuyerController::class, 'cart'])->name('cart');
    Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout');
    Route::post('/cart/preview-voucher', [BuyerController::class, 'previewVoucher'])->name('cart.preview-voucher');
    Route::post('/cart/add', [BuyerController::class, 'cartAdd'])->name('cart.add');
    Route::patch('/cart/{key}/edit', [BuyerController::class, 'cartEdit'])->where('key', '.*')->name('cart.edit');
    Route::patch('/cart/{key}', [BuyerController::class, 'cartUpdate'])->where('key', '.*')->name('cart.update');
    Route::delete('/cart/{key}', [BuyerController::class, 'cartRemove'])->where('key', '.*')->name('cart.remove');
    Route::get('/orders', [BuyerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/cancel', [BuyerController::class, 'cancelOrder'])->name('orders.cancel');
    Route::patch('/orders/{order}/confirm-receipt', [BuyerController::class, 'confirmReceipt'])->name('orders.confirm-receipt');
    Route::post('/orders/{order}/buy-again', [BuyerController::class, 'buyAgain'])->name('orders.buy-again');
    Route::post('/orders/{order}/review', [BuyerController::class, 'storeReview'])->name('orders.review');
    Route::get('/messages', [BuyerController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [BuyerController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [BuyerController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [BuyerController::class, 'messagesSend'])->name('messages.send');
    Route::get('/notifications/{id}/open', [BuyerController::class, 'openNotification'])->name('notifications.open');
    Route::get('/account', [BuyerController::class, 'account'])->name('account');
    Route::post('/payment-accounts/send-code', [BuyerController::class, 'sendPaymentAccountCode'])->name('payment-accounts.send-code');
    Route::post('/payment-accounts/verify', [BuyerController::class, 'verifyPaymentAccountCode'])->name('payment-accounts.verify');
    Route::delete('/payment-accounts/{account}', [BuyerController::class, 'destroyPaymentAccount'])->name('payment-accounts.destroy');
    Route::post('/logout', [BuyerController::class, 'logout'])->name('logout');
});

// Seller routes
Route::prefix('seller')->name('seller.')->middleware(['web', 'seller'])->group(function () {
    Route::get('/dashboard',     [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders',        [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/ready', [SellerController::class, 'readyForPickup'])->name('orders.ready');
    Route::get('/inventory',     [SellerController::class, 'inventory'])->name('inventory');
    Route::post('/inventory',     [SellerController::class, 'storeProduct'])->name('inventory.store');
    Route::patch('/inventory/{product}', [SellerController::class, 'updateProduct'])->name('inventory.update');
    Route::delete('/inventory/{product}', [SellerController::class, 'destroyProduct'])->name('inventory.destroy');
    Route::patch('/inventory/{product}/archive', [SellerController::class, 'archiveProduct'])->name('inventory.archive');
    Route::get('/notifications', [SellerController::class, 'notifications'])->name('notifications');
    Route::get('/orders/{order}/waybill', [SellerController::class, 'waybill'])->name('orders.waybill');
    Route::patch('/orders/{order}/schedule-pickup', [SellerController::class, 'schedulePickup'])->name('orders.schedule-pickup');
    Route::get('/feedback',      [SellerController::class, 'feedback'])->name('feedback');
    Route::post('/feedback/{review}/reply', [SellerController::class, 'replyReview'])->name('reviews.reply');
    Route::get('/reports',       [SellerController::class, 'reports'])->name('reports');
    Route::get('/messages',      [SellerController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [SellerController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [SellerController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [SellerController::class, 'messagesSend'])->name('messages.send');
    Route::post('/notifications/read', [SellerController::class, 'markNotifRead'])->name('notifications.read');
    Route::get('/notifications/{id}/open', [SellerController::class, 'openNotification'])->name('notifications.open');
    Route::get('/account',       [SellerController::class, 'account'])->name('account');
    Route::post('/account/profile',  [SellerController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/address',  [SellerController::class, 'updateAddress'])->name('account.address');
    Route::post('/account/shop',     [SellerController::class, 'updateShop'])->name('account.shop');
    Route::get('/vouchers',      [SellerController::class, 'vouchers'])->name('vouchers');
    Route::post('/vouchers',     [SellerController::class, 'storeVoucher'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}', [SellerController::class, 'updateVoucher'])->name('vouchers.update');
    Route::delete('/vouchers/{voucher}', [SellerController::class, 'destroyVoucher'])->name('vouchers.destroy');
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
    Route::get('/reports/export/sales-pdf', [AdminController::class, 'exportSalesReportPdf'])->name('reports.export.sales-pdf');
    Route::get('/reports/export/commission', [AdminController::class, 'exportCommissionReport'])->name('reports.export.commission');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::get('/announcements', [AdminController::class, 'announcements'])->name('announcements');
    Route::post('/settings/announcements', [AdminController::class, 'storeAnnouncement'])->name('settings.announcements.store');
    Route::delete('/settings/announcements/{id}', [AdminController::class, 'destroyAnnouncement'])->name('settings.announcements.destroy');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [AdminController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/send', [AdminController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{user}', [AdminController::class, 'messages'])->name('messages.user');
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
    Route::patch('/assignments/{id}/assign', [LogisticsController::class, 'assignCourier'])->name('assignments.assign');
    Route::get('/scan', [LogisticsController::class, 'scan'])->name('scan');
    Route::post('/scan/lookup', [LogisticsController::class, 'scanLookup'])->name('scan.lookup');
    Route::get('/monitor', [LogisticsController::class, 'monitor'])->name('monitor');
    Route::patch('/status/{id}', [LogisticsController::class, 'updateStatus'])->name('status.update');
    Route::get('/issues', [LogisticsController::class, 'issues'])->name('issues');
    Route::get('/history', [LogisticsController::class, 'history'])->name('history');
    Route::get('/reports', [LogisticsController::class, 'reports'])->name('reports');
    Route::get('/messages', [LogisticsController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [LogisticsController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [LogisticsController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [LogisticsController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{userId}', [LogisticsController::class, 'messagesThread'])->name('messages.thread');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::post('/account/update', [LogisticsController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/address', [LogisticsController::class, 'accountAddressUpdate'])->name('account.address');
    Route::post('/account/password', [LogisticsController::class, 'passwordUpdate'])->name('account.password');
});

// Rider (courier) routes
Route::prefix('rider')->name('rider.')->middleware('rider')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [RiderController::class, 'dashboard'])->name('dashboard');
    Route::get('/requests', [RiderController::class, 'requests'])->name('requests');
    Route::patch('/requests/{id}/accept', [RiderController::class, 'acceptRequest'])->name('requests.accept');
    Route::get('/deliveries', [RiderController::class, 'deliveries'])->name('deliveries');
    Route::get('/deliveries/{id}', [RiderController::class, 'show'])->name('deliveries.show');
    Route::patch('/deliveries/{id}/advance', [RiderController::class, 'advance'])->name('deliveries.advance');
    Route::get('/history', [RiderController::class, 'history'])->name('history');
    Route::get('/profit', [RiderController::class, 'profit'])->name('profit');
    Route::get('/messages', [RiderController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [RiderController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [RiderController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [RiderController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{userId}', [RiderController::class, 'messagesThread'])->name('messages.thread');
    Route::get('/account', [RiderController::class, 'account'])->name('account');
    Route::post('/account/update', [RiderController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/address', [RiderController::class, 'accountAddressUpdate'])->name('account.address');
    Route::post('/account/password', [RiderController::class, 'passwordUpdate'])->name('account.password');
});
