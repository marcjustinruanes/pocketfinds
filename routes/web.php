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
use App\Http\Controllers\SupportController;
use App\Models\Message;
use App\Models\Complaint;
use App\Models\Policy;
use Illuminate\Support\Facades\Storage;

Route::get('/', [GuestController::class, 'home']);
Route::get('/product/{id}', [GuestController::class, 'product'])->name('guest.product');
Route::get('/shop/{slug}', [GuestController::class, 'shop'])->name('guest.shop');
// Sends the message itself via the server — unlike a mailto: link, this works the
// same for every visitor regardless of what mail client their browser is set to use.
Route::post('/support/contact', [SupportController::class, 'contact'])->name('support.contact');

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
    // Terms & Conditions vary by role — each has its own admin-editable document.
    $registerView = function (string $view, string $accountType) {
        $isGoogleSignup = request()->boolean('google') && session()->has('google_email') && session('google_email');
        if (!$isGoogleSignup) {
            session()->forget(['google_id', 'google_name', 'google_email', 'google_avatar']);
        }
        return view($view, [
            'isGoogleSignup' => $isGoogleSignup,
            'googleId'       => $isGoogleSignup ? session('google_id') : null,
            'googleEmail'    => $isGoogleSignup ? session('google_email') : null,
            'terms'          => Policy::where('type', 'terms_and_conditions')->where('account_type', $accountType)->first(),
            // Riders and hub staff join an existing logistics company — they pick from
            // the approved company list and see that company's own T&C.
            // Logistics company registration always founds a new company, so it only
            // needs the platform-wide T&C (fetched above via account_type='logistics').
            'logisticsCompanies' => in_array($accountType, ['rider', 'logistics-staff'], true)
                ? \App\Models\LogisticsCompany::approved()
                : null,
            'companyPolicies' => in_array($accountType, ['rider', 'logistics-staff'], true)
                ? Policy::where('type', 'logistics_company_terms')->get()->keyBy('company_name')
                : null,
            // Every approved company's registered hubs, grouped by company — lets the
            // hub-staff registration show its match live, client-side, without a round
            // trip. Small dataset (one row per municipality a company covers).
            'companyHubs' => in_array($accountType, ['logistics-staff', 'rider'], true)
                ? \App\Models\LogisticsHub::orderBy('province')->orderBy('municipality')
                    ->get(['id', 'company_name', 'province', 'municipality', 'is_regional_hub', 'is_hiring'])
                    // Riders picking "Company Vehicle" need to know, per hub, which vehicle
                    // types that hub actually has an approved+available unit of — used to
                    // disable a vehicle-type card that hub simply can't provide right now.
                    // One query for every hub's available types, not one query PER hub (this
                    // page loads every approved company's hubs, so that was a real N+1).
                    ->when($accountType === 'rider', function ($hubs) {
                        $availableTypesByHub = \App\Models\CompanyVehicle::whereIn('logistics_hub_id', $hubs->pluck('id'))
                            ->riderVisible()->get(['logistics_hub_id', 'vehicle_type'])
                            ->groupBy('logistics_hub_id')
                            ->map(fn ($vehicles) => $vehicles->pluck('vehicle_type')->unique()->values()->all());
                        $hubs->each(fn ($hub) => $hub->available_vehicle_types = $availableTypesByHub->get($hub->id, []));
                    })
                    ->groupBy('company_name')
                : null,
        ]);
    };
    Route::get('/register/buyer', fn () => $registerView('auth.register-buyer', 'buyer'))->name('register.buyer');
    Route::get('/register/seller', fn () => $registerView('auth.register-seller', 'seller'))->name('register.seller');
    Route::get('/register/rider', fn () => $registerView('auth.register-rider', 'rider'))->name('register.rider');
    // Logistics company registration (founding a new company — always "found" mode).
    Route::get('/register/logistics', fn () => $registerView('auth.register-logistics', 'logistics'))->name('register.logistics');
    // Hub staff registration (joining an existing company — always "join" mode).
    Route::get('/register/logistics-staff', fn () => $registerView('auth.register-logistics-staff', 'logistics-staff'))->name('register.logistics-staff');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    // Legacy shared buyer/seller page — kept so any old bookmark/link still resolves,
    // but nothing in the app links here anymore; use register.buyer/register.seller above.
    Route::get('/register', fn () => $registerView('auth.register', in_array(request('type'), ['buyer', 'seller'], true) ? request('type') : 'buyer'))->name('register');
    Route::get('/register/categories', [RegisterController::class, 'categories'])->name('register.categories');
    Route::post('/register/send-otp', [RegisterController::class, 'sendOtp'])->name('register.send-otp');
    Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])->name('register.verify-otp');
    Route::get('/register/check-username', [RegisterController::class, 'checkUsername'])->name('register.check-username');
    Route::get('/register/check-business-name', [RegisterController::class, 'checkBusinessName'])->name('register.check-business-name');
    Route::get('/register/check-license', [RegisterController::class, 'checkLicenseNumber'])->name('register.check-license');
    Route::get('/register/check-plate', [RegisterController::class, 'checkPlateNumber'])->name('register.check-plate');
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
    Route::post('/shop/{slug}/follow', [BuyerController::class, 'followShop'])->name('shop.follow');
    Route::get('/cart', [BuyerController::class, 'cart'])->name('cart');
    Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout');
    Route::post('/addresses', [BuyerController::class, 'storeAddress'])->name('addresses.store');
    Route::delete('/addresses/{address}', [BuyerController::class, 'destroyAddress'])->name('addresses.destroy');
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
    Route::post('/account/profile', [BuyerController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/address', [BuyerController::class, 'updateAddress'])->name('account.address');
    Route::post('/account/password', [BuyerController::class, 'passwordUpdate'])->name('account.password');
    Route::post('/payment-accounts/send-code', [BuyerController::class, 'sendPaymentAccountCode'])->name('payment-accounts.send-code');
    Route::post('/payment-accounts/verify', [BuyerController::class, 'verifyPaymentAccountCode'])->name('payment-accounts.verify');
    Route::delete('/payment-accounts/{account}', [BuyerController::class, 'destroyPaymentAccount'])->name('payment-accounts.destroy');
    Route::post('/logout', [BuyerController::class, 'logout'])->name('logout');
});

// Seller routes
Route::prefix('seller')->name('seller.')->middleware(['web', 'seller'])->group(function () {
    Route::get('/dashboard',     [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders',        [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/confirm', [SellerController::class, 'confirmOrder'])->name('orders.confirm');
    Route::patch('/orders/{order}/preparing', [SellerController::class, 'startPreparing'])->name('orders.preparing');
    Route::patch('/orders/{order}/ready', [SellerController::class, 'readyForPickup'])->name('orders.ready');
    Route::patch('/orders/{order}/confirm-pickup', [SellerController::class, 'confirmPickup'])->name('orders.confirm-pickup');
    Route::get('/inventory',     [SellerController::class, 'inventory'])->name('inventory');
    Route::post('/inventory',     [SellerController::class, 'storeProduct'])->name('inventory.store');
    Route::patch('/inventory/{product}', [SellerController::class, 'updateProduct'])->name('inventory.update');
    Route::delete('/inventory/{product}', [SellerController::class, 'destroyProduct'])->name('inventory.destroy');
    Route::patch('/inventory/{product}/archive', [SellerController::class, 'archiveProduct'])->name('inventory.archive');
    Route::post('/inventory/{product}/add-stock', [SellerController::class, 'addStock'])->name('inventory.add-stock');
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
    Route::post('/policies/{accountType}', [AdminController::class, 'updatePolicy'])->name('policies.update');
    Route::post('/settings/general', [AdminController::class, 'updateGeneralSettings'])->name('settings.general.update');
    Route::post('/settings/toggles', [AdminController::class, 'updateFeatureToggles'])->name('settings.toggles.update');
    Route::post('/settings/cache/clear', [AdminController::class, 'clearCache'])->name('settings.cache.clear');
    Route::post('/settings/sessions/clear', [AdminController::class, 'clearSessions'])->name('settings.sessions.clear');
    Route::post('/settings/preferences', [AdminController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [AdminController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/send', [AdminController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{user}', [AdminController::class, 'messages'])->name('messages.user');
    Route::post('/messages/{user}', [AdminController::class, 'sendMessage'])->name('messages.send');
    Route::post('/messages/react/{message}', [AdminController::class, 'reactMessage'])->name('messages.react');
    Route::get('/account', [AdminController::class, 'account'])->name('account');
    Route::post('/account/update', [AdminController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/password', [AdminController::class, 'passwordUpdate'])->name('account.password');
    Route::get('/update-requests', [AdminController::class, 'updateRequests'])->name('update-requests');
    Route::patch('/update-requests/{id}/approve', [AdminController::class, 'approveUpdateRequest'])->name('update-requests.approve');
    Route::patch('/update-requests/{id}/reject', [AdminController::class, 'rejectUpdateRequest'])->name('update-requests.reject');
    Route::get('/products', [AdminController::class, 'products'])->name('products');
    Route::patch('/products/{id}/approve', [AdminController::class, 'approveProduct'])->name('products.approve');
    Route::patch('/products/{id}/reject', [AdminController::class, 'rejectProduct'])->name('products.reject');
    Route::get('/policies', [AdminController::class, 'policies'])->name('policies');
    Route::patch('/policies/companies/{companyName}/approve', [AdminController::class, 'approveCompanyPolicy'])->name('policies.company.approve')->where('companyName', '.*');
    Route::post('/policies/companies/{companyName}/revisions', [AdminController::class, 'requestCompanyPolicyRevisions'])->name('policies.company.revisions')->where('companyName', '.*');
    // Company vehicle review — platform admin gate before vehicles appear to riders.
    Route::get('/vehicles', [AdminController::class, 'vehicles'])->name('vehicles');
    Route::patch('/vehicles/{id}/approve', [AdminController::class, 'approveVehicle'])->name('vehicles.approve');
    Route::patch('/vehicles/{id}/reject', [AdminController::class, 'rejectVehicle'])->name('vehicles.reject');
});

// Logistics routes
Route::prefix('logistics')->name('logistics.')->middleware('logistics')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/assignments', [LogisticsController::class, 'assignments'])->name('assignments');
    Route::patch('/assignments/{id}/assign', [LogisticsController::class, 'assignCourier'])->name('assignments.assign');
    Route::patch('/assignments/{id}/assign-hub-transfer', [LogisticsController::class, 'assignHubTransferRider'])->name('assignments.assign-hub-transfer');
    Route::get('/scan', [LogisticsController::class, 'scan'])->name('scan');
    Route::post('/scan/lookup', [LogisticsController::class, 'scanLookup'])->name('scan.lookup');
    Route::patch('/scan/{id}/receive', [LogisticsController::class, 'receiveAtSortingCenter'])->name('scan.receive');
    Route::patch('/scan/{id}/complete-hub-transfer', [LogisticsController::class, 'completeHubTransfer'])->name('scan.complete-hub-transfer');
    Route::post('/hub/request-transfer', [LogisticsController::class, 'requestHubTransfer'])->name('hub.request-transfer');
    Route::get('/monitor', [LogisticsController::class, 'monitor'])->name('monitor');
    Route::patch('/status/{id}', [LogisticsController::class, 'updateStatus'])->name('status.update');
    Route::get('/issues', [LogisticsController::class, 'issues'])->name('issues');
    Route::get('/history', [LogisticsController::class, 'history'])->name('history');
    Route::get('/messages', [LogisticsController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [LogisticsController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [LogisticsController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [LogisticsController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{userId}', [LogisticsController::class, 'messagesThread'])->name('messages.thread');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::post('/account/update', [LogisticsController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/address', [LogisticsController::class, 'accountAddressUpdate'])->name('account.address');
    Route::post('/account/password', [LogisticsController::class, 'passwordUpdate'])->name('account.password');

    // Company-wide oversight — the admin account only, never hub staff (see LogisticsAdminMiddleware).
    Route::middleware('logistics.admin')->group(function () {
        Route::get('/requests', [LogisticsController::class, 'requests'])->name('requests');
        Route::patch('/requests/{id}/approve', [LogisticsController::class, 'approveRequest'])->name('requests.approve');
        Route::patch('/requests/{id}/reject', [LogisticsController::class, 'rejectRequest'])->name('requests.reject');
        Route::get('/hub-requests', [LogisticsController::class, 'hubRequests'])->name('hub-requests');
        Route::patch('/hub-requests/approve', [LogisticsController::class, 'approveHubTransferRequest'])->name('hub-requests.approve');
        Route::patch('/hub-requests/reject', [LogisticsController::class, 'rejectHubTransferRequest'])->name('hub-requests.reject');
        Route::patch('/hub-requests/admin-request', [LogisticsController::class, 'adminRequestHubTransfer'])->name('hub-requests.admin-request');
        Route::get('/reports', [LogisticsController::class, 'reports'])->name('reports');
        Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
        Route::patch('/riders/{id}/approve', [LogisticsController::class, 'approveRider'])->name('riders.approve');
        Route::patch('/riders/{id}/reject', [LogisticsController::class, 'rejectRider'])->name('riders.reject');
        Route::patch('/riders/{id}/activate', [LogisticsController::class, 'activateRider'])->name('riders.activate');
        Route::patch('/riders/{id}/suspend', [LogisticsController::class, 'suspendRider'])->name('riders.suspend');
        Route::get('/staff', [LogisticsController::class, 'staff'])->name('staff');
        // "Approve" on a fresh application invites the applicant to a face-to-face interview
        // instead of granting login directly; "Confirm" is the real, final approval, meant to
        // be clicked after that interview has actually happened (see LogisticsController).
        Route::patch('/staff/{id}/approve', [LogisticsController::class, 'inviteStaffInterview'])->name('staff.approve');
        Route::patch('/staff/{id}/confirm', [LogisticsController::class, 'confirmStaffApproval'])->name('staff.confirm');
        Route::patch('/staff/{id}/reject', [LogisticsController::class, 'rejectStaff'])->name('staff.reject');
        Route::patch('/staff/{id}/activate', [LogisticsController::class, 'activateStaff'])->name('staff.activate');
        Route::patch('/staff/{id}/suspend', [LogisticsController::class, 'suspendStaff'])->name('staff.suspend');
        Route::get('/hubs', [LogisticsController::class, 'hubs'])->name('hubs');
        Route::patch('/hubs/{id}/toggle-hiring', [LogisticsController::class, 'toggleHubHiring'])->name('hubs.toggle-hiring');
        Route::post('/company-policy', [LogisticsController::class, 'updateCompanyPolicy'])->name('company-policy.update');

        // Company-wide fleet — logistics admin submits vehicles (assigned to a hub),
        // platform admin approves them before they show on rider registration.
        Route::get('/vehicles', [LogisticsController::class, 'fleet'])->name('vehicles');
        Route::post('/vehicles', [LogisticsController::class, 'storeVehicle'])->name('vehicles.store');
        Route::delete('/vehicles/{id}', [LogisticsController::class, 'destroyVehicle'])->name('vehicles.destroy');
    });

    // Hub staff's own hub fleet — view vehicles assigned to their hub and toggle availability.
    // Guarded inside the controller (isHubStaff()) rather than LogisticsAdminMiddleware.
    Route::get('/hub/vehicles', [LogisticsController::class, 'hubVehicles'])->name('hub.vehicles');
    Route::patch('/hub/vehicles/{id}/toggle', [LogisticsController::class, 'toggleVehicleAvailability'])->name('hub.vehicles.toggle');

    // Pickup-leg assignment — origin hub staff assign a rider for a request that
    // originates at their own hub; the admin can do this for any hub. Not under
    // logistics.admin (unlike /requests' approve/reject) so hub staff can reach it too.
    Route::patch('/requests/{id}/assign-pickup', [LogisticsController::class, 'assignPickupRider'])->name('requests.assign-pickup');
});

// Rider (courier) routes
Route::prefix('rider')->name('rider.')->middleware('rider')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [RiderController::class, 'dashboard'])->name('dashboard');
    Route::get('/pickup-requests', [RiderController::class, 'pickupRequests'])->name('pickup-requests');
    Route::patch('/pickup-requests/{id}/accept', [RiderController::class, 'acceptPickupRequest'])->name('pickup-requests.accept');
    Route::get('/my-pickups', [RiderController::class, 'myPickups'])->name('my-pickups');
    Route::patch('/my-pickups/{id}/confirm', [RiderController::class, 'confirmPickupReceipt'])->name('my-pickups.confirm');
    Route::get('/hub-transfers', [RiderController::class, 'myHubTransfers'])->name('hub-transfers');
    Route::patch('/hub-transfers/{id}/confirm', [RiderController::class, 'confirmHubTransferPickup'])->name('hub-transfers.confirm');
    Route::get('/requests', [RiderController::class, 'requests'])->name('requests');
    Route::patch('/requests/{id}/accept', [RiderController::class, 'acceptRequest'])->name('requests.accept');
    Route::get('/deliveries', [RiderController::class, 'deliveries'])->name('deliveries');
    Route::get('/deliveries/{id}', [RiderController::class, 'show'])->name('deliveries.show');
    Route::patch('/deliveries/{id}/advance', [RiderController::class, 'advance'])->name('deliveries.advance');
    Route::patch('/deliveries/{id}/failed', [RiderController::class, 'markFailed'])->name('deliveries.failed');
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
