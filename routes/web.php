<?php

\Illuminate\Support\Facades\Route::get('/private-documents/{token}', [\App\Http\Controllers\SensitiveDocumentController::class, 'show'])
    ->middleware(['auth', 'throttle:240,1'])->name('sensitive-documents.show');

use App\Http\Controllers\AdminAssistantController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BuyerAssistantController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerConversationController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GcashPurchaseController;
use App\Http\Controllers\GuestAssistantController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HubTransferController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\LogisticsManagementController;
use App\Http\Controllers\PhoneScannerController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SupportController;
use App\Models\CompanyVehicle;
use App\Models\Complaint;
use App\Models\LogisticsCompany;
use App\Models\LogisticsHub;
use App\Models\Message;
use App\Models\Policy;
use App\Services\HubHiring;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/commerce.php';

Route::get('/', [GuestController::class, 'home']);
Route::get('/assistant', [GuestAssistantController::class, 'history'])->middleware('throttle:30,1')->name('guest.assistant.history');
Route::post('/assistant', [GuestAssistantController::class, 'send'])->middleware('throttle:6,1')->name('guest.assistant.send');
Route::delete('/assistant', [GuestAssistantController::class, 'clear'])->middleware('throttle:10,1')->name('guest.assistant.clear');
Route::get('/categories/{id}/image', [GuestController::class, 'categoryImage'])->whereNumber('id')->name('guest.category-image');
Route::get('/product/{id}', [GuestController::class, 'product'])->name('guest.product');
Route::get('/shop/{slug}', [GuestController::class, 'shop'])->name('guest.shop');
// Sends the message itself via the server — unlike a mailto: link, this works the
// same for every visitor regardless of what mail client their browser is set to use.
Route::post('/support/contact', [SupportController::class, 'contact'])->middleware('throttle:3,1')->name('support.contact');

Route::middleware('web', 'auth')->get('/message-media/{path}', function (string $path) {
    $message = Message::where('attachment_path', $path)
        ->where(function ($query) {
            $query->where('sender_id', auth()->id())->orWhere('receiver_id', auth()->id());
        })->firstOrFail();

    // Stream private local attachments only after checking conversation membership.
    abort_unless(Storage::disk('messages')->exists($message->attachment_path), 404);

    return response()->file(Storage::disk('messages')->path($message->attachment_path));
})->where('path', '.*')->name('message.media');

Route::middleware('web', 'auth')->get('/report-evidence/{path}', function (string $path) {
    $complaint = Complaint::where('evidence_path', $path)->firstOrFail();
    abort_unless(auth()->user()->is_admin || auth()->id() === $complaint->complainant_id || auth()->id() === $complaint->respondent_id, 403);
    abort_unless(Storage::disk('public')->exists($complaint->evidence_path), 404);

    return response()->file(Storage::disk('public')->path($complaint->evidence_path));
})->where('path', '.*')->name('report.evidence');

Route::middleware('web')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [AdminController::class, 'loginPost'])->name('login.post');
    Route::get('/register/type', fn () => view('auth.account-type'))->name('register.type');
    // Rider/Logistics ("delivery team") are internal/operational roles kept off the main
    // customer role picker above — reached only via its own explicit "join the delivery
    // team" link, not listed alongside Buyer/Seller.
    Route::get('/register/delivery-team', fn () => view('auth.delivery-type'))->name('register.delivery-team');
    Route::get('/register/method', [RegisterController::class, 'method'])->name('register.method');

    // Every role (buyer, seller, rider, logistics) gets its own dedicated registration
    // page/view — same google-signup-detection boilerplate for each, factored out here.
    // Terms & Conditions vary by role — each has its own admin-editable document.
    $registerView = function (string $view, string $accountType) {
        $isGoogleSignup = request()->boolean('google') && session()->has('google_email') && session('google_email');
        if (! $isGoogleSignup) {
            session()->forget(['google_id', 'google_name', 'google_email', 'google_avatar']);
        }

        return view($view, [
            'isGoogleSignup' => $isGoogleSignup,
            'googleId' => $isGoogleSignup ? session('google_id') : null,
            'googleEmail' => $isGoogleSignup ? session('google_email') : null,
            'terms' => Policy::where('type', 'terms_and_conditions')->where('account_type', $accountType)->first(),
            // Riders and hub staff join an existing logistics company — they pick from
            // the approved company list and see that company's own T&C.
            // Logistics company registration always founds a new company, so it only
            // needs the platform-wide T&C (fetched above via account_type='logistics').
            'logisticsCompanies' => in_array($accountType, ['rider', 'logistics-staff'], true)
                ? LogisticsCompany::approved()
                : null,
            'companyPolicies' => in_array($accountType, ['rider', 'logistics-staff'], true)
                ? Policy::where('type', 'logistics_company_terms')->get()->keyBy('company_name')
                : null,
            // Every approved company's registered hubs, grouped by company — lets the
            // hub-staff registration show its match live, client-side, without a round
            // trip. Small dataset (one row per municipality a company covers).
            'companyHubs' => in_array($accountType, ['logistics-staff', 'rider'], true)
                ? LogisticsHub::orderBy('province')->orderBy('municipality')
                    ->where('is_operational', true)
                    ->select(['id', 'company_name', 'province', 'municipality', 'is_regional_hub', 'is_hiring', 'is_operational',
                        'rider_hiring_open', 'staff_hiring_open', 'rider_hire_limit', 'staff_hire_limit', 'rider_hiring_closes_at', 'staff_hiring_closes_at'])
                    ->withCount([
                        'riders as approved_rider_count' => fn ($q) => $q->whereColumn('users.business_name', 'logistics_hubs.company_name')->where('status', 'approved')->where('is_admin', false)->where('is_logistics', false),
                        'staff as approved_staff_count' => fn ($q) => $q->whereColumn('users.business_name', 'logistics_hubs.company_name')->where('status', 'approved')->where('is_admin', false),
                    ])
                    ->get()
                    ->each(function ($hub) use ($accountType) {
                        $state = app(HubHiring::class)->availability($hub, $accountType === 'rider' ? 'rider' : 'hub_staff', $accountType === 'rider' ? $hub->approved_rider_count : $hub->approved_staff_count);
                        $hub->is_hiring = $state['eligible'];
                        $hub->hiring_status = $state['status'];
                    })
                    // Riders picking "Company Vehicle" need to know, per hub, which vehicle
                    // types that hub actually has an approved+available unit of — used to
                    // disable a vehicle-type card that hub simply can't provide right now.
                    // One query for every hub's available types, not one query PER hub (this
                    // page loads every approved company's hubs, so that was a real N+1).
                    ->when($accountType === 'rider', function ($hubs) {
                        $availableTypesByHub = CompanyVehicle::whereIn('logistics_hub_id', $hubs->pluck('id'))
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
    Route::get('/password/reset', fn () => view('auth.login'))->name('password.request');
    Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/auth/google/login', [SocialAuthController::class, 'redirectToGoogleLogin'])->name('google.login');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('google.callback');
});

// Buyer routes
Route::prefix('buyer')->name('buyer.')->middleware(['web', 'buyer'])->group(function () {
    Route::get('/faqs', [FaqController::class, 'buyer'])->name('faqs');
    Route::get('/assistant/conversations', [BuyerConversationController::class, 'index'])->middleware('throttle:60,1')->name('assistant.conversations');
    Route::post('/assistant/conversations', [BuyerConversationController::class, 'create'])->middleware('throttle:20,1');
    Route::get('/assistant/conversations/{conversation}', [BuyerConversationController::class, 'show'])->middleware('throttle:60,1');
    Route::patch('/assistant/conversations/{conversation}', [BuyerConversationController::class, 'update'])->middleware('throttle:30,1');
    Route::delete('/assistant/conversations/{conversation}', [BuyerConversationController::class, 'delete'])->middleware('throttle:20,1');
    Route::post('/assistant/conversations/{conversation}/messages', [BuyerConversationController::class, 'send'])->middleware('throttle:10,1');
    Route::get('/assistant', [BuyerAssistantController::class, 'history'])->middleware('throttle:30,1')->name('assistant.history');
    Route::post('/assistant', [BuyerAssistantController::class, 'send'])->middleware('throttle:10,1')->name('assistant.send');
    Route::delete('/assistant', [BuyerAssistantController::class, 'clear'])->middleware('throttle:10,1')->name('assistant.clear');
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
    Route::get('/announcements', [BuyerController::class, 'announcements'])->name('announcements');
    Route::get('/announcements/{id}', [BuyerController::class, 'showAnnouncement'])->name('announcements.show');
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
    Route::get('/orders/assignment-review', [\App\Http\Controllers\LogisticsOperationsController::class,'index'])->name('assignments.review');
    Route::post('/orders/assignments/{id}/decision', [\App\Http\Controllers\LogisticsOperationsController::class,'decideReassignment'])->middleware('throttle:20,1')->name('assignments.decide');
    Route::get('/payouts', [\App\Http\Controllers\SellerPayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts/destination', [\App\Http\Controllers\SellerPayoutController::class, 'destination'])->middleware('throttle:10,1')->name('payouts.destination');
    Route::post('/payouts/{id}/acknowledge', [\App\Http\Controllers\SellerPayoutController::class, 'acknowledge'])->middleware('throttle:10,1')->name('payouts.acknowledge');
    Route::get('/shipping', [\App\Http\Controllers\LogisticsShippingController::class, 'sellerIndex'])->name('shipping');
    Route::get('/shipping/{id}', [\App\Http\Controllers\LogisticsShippingController::class, 'show'])->name('shipping.show');
    Route::post('/shipping/{id}/payment', [\App\Http\Controllers\LogisticsShippingController::class, 'submit'])->middleware('throttle:10,1')->name('shipping.submit');
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/confirm', [SellerController::class, 'confirmOrder'])->name('orders.confirm');
    Route::patch('/orders/{order}/cancel', [SellerController::class, 'cancelOrder'])->name('orders.cancel');
    Route::patch('/orders/{order}/preparing', [SellerController::class, 'startPreparing'])->name('orders.preparing');
    Route::patch('/orders/{order}/ready', [SellerController::class, 'readyForPickup'])->name('orders.ready');
    Route::post('/orders/rts/{id}', [LogisticsManagementController::class,'rts'])->middleware('throttle:20,1')->name('orders.rts');
    Route::patch('/orders/{order}/confirm-pickup', [SellerController::class, 'confirmPickup'])->name('orders.confirm-pickup');
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory');
    Route::post('/inventory', [SellerController::class, 'storeProduct'])->name('inventory.store');
    Route::patch('/inventory/{product}', [SellerController::class, 'updateProduct'])->name('inventory.update');
    Route::delete('/inventory/{product}', [SellerController::class, 'destroyProduct'])->name('inventory.destroy');
    Route::patch('/inventory/{product}/archive', [SellerController::class, 'archiveProduct'])->name('inventory.archive');
    Route::post('/inventory/{product}/add-stock', [SellerController::class, 'addStock'])->name('inventory.add-stock');
    Route::get('/notifications', [SellerController::class, 'notifications'])->name('notifications');
    Route::get('/orders/{order}/waybill', [SellerController::class, 'waybill'])->name('orders.waybill');
    Route::patch('/orders/{order}/schedule-pickup', [SellerController::class, 'schedulePickup'])->name('orders.schedule-pickup');
    Route::get('/feedback', [SellerController::class, 'feedback'])->name('feedback');
    Route::post('/feedback/{review}/reply', [SellerController::class, 'replyReview'])->name('reviews.reply');
    Route::get('/reports', [SellerController::class, 'reports'])->name('reports');
    Route::get('/messages', [SellerController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [SellerController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [SellerController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [SellerController::class, 'messagesSend'])->name('messages.send');
    Route::post('/notifications/read', [SellerController::class, 'markNotifRead'])->name('notifications.read');
    Route::get('/notifications/{id}/open', [SellerController::class, 'openNotification'])->name('notifications.open');
    Route::get('/announcements', [SellerController::class, 'announcements'])->name('announcements');
    Route::post('/announcements', [SellerController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [SellerController::class, 'destroyAnnouncement'])->name('announcements.destroy');
    Route::get('/account', [SellerController::class, 'account'])->name('account');
    Route::post('/account/profile', [SellerController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/address', [SellerController::class, 'updateAddress'])->name('account.address');
    Route::post('/account/shop', [SellerController::class, 'updateShop'])->name('account.shop');
    Route::post('/account/shop-photo', [SellerController::class, 'updateShopPhoto'])->name('account.shop-photo');
    Route::get('/vouchers', [SellerController::class, 'vouchers'])->name('vouchers');
    Route::post('/vouchers', [SellerController::class, 'storeVoucher'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}', [SellerController::class, 'updateVoucher'])->name('vouchers.update');
    Route::delete('/vouchers/{voucher}', [SellerController::class, 'destroyVoucher'])->name('vouchers.destroy');
    Route::post('/account/documents', [SellerController::class, 'updateDocuments'])->name('account.documents');
    Route::post('/account/password', [SellerController::class, 'updatePassword'])->name('account.password');
    Route::post('/logout', [SellerController::class, 'logout'])->name('logout');
});

// Admin routes
Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
    Route::get('/faqs', [AdminController::class, 'faqs'])->name('faqs');
    Route::post('/faqs', [FaqController::class, 'save'])->middleware('throttle:30,1')->name('faqs.store');
    Route::put('/faqs/{faq}', [FaqController::class, 'save'])->middleware('throttle:30,1')->name('faqs.update');
    Route::delete('/faqs/{faq}', [FaqController::class, 'delete'])->middleware('throttle:30,1')->name('faqs.delete');
    Route::get('/ai', [AdminController::class, 'aiWorkspace'])->name('ai');
    Route::get('/ai/export', [AdminAssistantController::class, 'export'])->middleware('throttle:10,1')->name('ai.export');
    Route::get('/ai/orders/{order}', [AdminController::class, 'aiOrderReview'])->name('ai.order');
    Route::get('/ai/products/{product}/photo', [AdminAssistantController::class, 'photo'])->name('ai.photo');
    Route::post('/ai/analyze', [AdminAssistantController::class, 'analyze'])->middleware('throttle:6,1')->name('ai.analyze');
    Route::delete('/ai/chat', [AdminAssistantController::class, 'clear'])->name('ai.clear');
    Route::post('/ai/prepare-draft', [AdminAssistantController::class, 'prepareDraft'])->name('ai.prepare-draft');
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
    Route::post('/settings/hero', [AdminController::class, 'updateHeroSettings'])->name('settings.hero.update');
    Route::post('/settings/categories/{id}/photo', [AdminController::class, 'updateCategoryPhoto'])->whereNumber('id')->name('settings.category-photo.update');
    Route::post('/settings/toggles', [AdminController::class, 'updateFeatureToggles'])->name('settings.toggles.update');
    Route::post('/settings/cache/clear', [AdminController::class, 'clearCache'])->name('settings.cache.clear');
    Route::post('/settings/sessions/clear', [AdminController::class, 'clearSessions'])->name('settings.sessions.clear');
    Route::post('/settings/preferences', [AdminController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::get('/messages', [AdminController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [AdminController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/send', [AdminController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{user}', [AdminController::class, 'messages'])->name('messages.user');
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
Route::prefix('logistics')->name('logistics.')->middleware(['logistics', 'logistics.activity'])->group(function () {
    Route::get('/cash-desk', [\App\Http\Controllers\LogisticsCashDeskController::class, 'index'])->name('cash-desk');
    Route::post('/cash-desk/recovery', [\App\Http\Controllers\LogisticsCashDeskController::class,'recoverySubmission'])->middleware('throttle:20,1')->name('cash-desk.recovery');
    Route::post('/cash-desk/treasury/{id}/release', [\App\Http\Controllers\SellerPayoutController::class, 'release'])->middleware('throttle:10,1')->name('cash-desk.treasury.release');
    Route::post('/cash-desk/{id}/count', [\App\Http\Controllers\LogisticsCashDeskController::class, 'count'])->middleware('throttle:20,1')->name('cash-desk.count');
    Route::post('/cash-desk/transfers', [\App\Http\Controllers\LogisticsCashDeskController::class, 'authorize'])->middleware('throttle:20,1')->name('cash-desk.authorize');
    Route::post('/cash-desk/{id}/transfer/{action}', [\App\Http\Controllers\LogisticsCashDeskController::class, 'transfer'])->middleware('throttle:20,1')->name('cash-desk.transfer');
    Route::post('/cash-desk/{id}/resolve', [\App\Http\Controllers\LogisticsCashDeskController::class, 'resolve'])->middleware('throttle:20,1')->name('cash-desk.resolve');
    Route::post('/cash-desk/cutoff', [\App\Http\Controllers\LogisticsCashDeskController::class, 'cutoff'])->middleware('throttle:10,1')->name('cash-desk.cutoff');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/transfers', [HubTransferController::class, 'index'])->name('transfers');
    Route::post('/transfers', [HubTransferController::class, 'store'])->name('transfers.store');
    Route::get('/transfers/request', [HubTransferController::class, 'create'])->name('transfers.create');
    Route::get('/transfers/{id}', [HubTransferController::class, 'show'])->name('transfers.show');
    Route::patch('/transfers/{id}/cancel', [HubTransferController::class, 'cancel'])->name('transfers.cancel');
    Route::patch('/transfers/{id}/receive', [HubTransferController::class, 'receive'])->name('transfers.receive');
    Route::post('/transfers/{id}/discrepancy', [HubTransferController::class, 'discrepancy'])->name('transfers.discrepancy');
    Route::middleware('logistics.admin')->group(function () {
        Route::patch('/transfers/{id}/approve', [HubTransferController::class, 'approve'])->name('transfers.approve');
        Route::patch('/transfers/{id}/reject', [HubTransferController::class, 'reject'])->name('transfers.reject');
        Route::patch('/transfers/{id}/dispatch', [HubTransferController::class, 'dispatch'])->name('transfers.dispatch');
    });
    Route::get('/assignments', [LogisticsController::class, 'assignments'])->name('assignments');
    Route::get('/assignments/review', [\App\Http\Controllers\LogisticsOperationsController::class,'index'])->name('assignments.review');
    Route::post('/assignments/{id}/reassignment', [\App\Http\Controllers\LogisticsOperationsController::class,'requestReassignment'])->middleware('throttle:20,1')->name('assignments.reassign');
    Route::post('/assignments/{id}/reassignment/decision', [\App\Http\Controllers\LogisticsOperationsController::class,'decideReassignment'])->middleware('throttle:20,1')->name('assignments.decide');
    Route::patch('/assignments/{id}/assign', [LogisticsController::class, 'assignCourier'])->name('assignments.assign');
    Route::patch('/assignments/{id}/assign-hub-transfer', [LogisticsController::class, 'assignHubTransferRider'])->middleware('logistics.admin')->name('assignments.assign-hub-transfer');
    Route::get('/scan', [LogisticsController::class, 'scan'])->name('scan');
    Route::post('/scan/lookup', [LogisticsController::class, 'scanLookup'])->name('scan.lookup');
    // Short-lived phone relay: lookup input only, never shipment/status writes.
    Route::withoutMiddleware('logistics.activity')->group(function () {
        Route::post('/scan/phone-sessions', [PhoneScannerController::class, 'create'])->middleware('throttle:10,1,phone-create:')->name('scan.phone-sessions');
        Route::get('/scan/phone/{token}', [PhoneScannerController::class, 'phone'])->where('token', '[A-Za-z0-9]{64}')->name('scan.phone');
        Route::get('/scan/phone/{token}/events', [PhoneScannerController::class, 'poll'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:60,1,phone-poll:')->name('scan.phone.events');
        Route::post('/scan/phone/{token}/codes', [PhoneScannerController::class, 'send'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:60,1,phone-codes:')->name('scan.phone.codes');
        Route::post('/scan/phone/{token}/ack', [PhoneScannerController::class, 'acknowledge'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:60,1,phone-ack:')->name('scan.phone.ack');
        Route::delete('/scan/phone/{token}', [PhoneScannerController::class, 'disconnect'])->where('token', '[A-Za-z0-9]{64}')->name('scan.phone.disconnect');
    });

    Route::patch('/scan/{id}/receive', [LogisticsController::class, 'receiveAtSortingCenter'])->name('scan.receive');
    Route::patch('/scan/{id}/complete-hub-transfer', [LogisticsController::class, 'completeHubTransfer'])->name('scan.complete-hub-transfer');
    Route::post('/hub/request-transfer', [LogisticsController::class, 'requestHubTransfer'])->name('hub.request-transfer');
    Route::get('/monitor', [LogisticsController::class, 'monitor'])->name('monitor');
    Route::patch('/status/{id}', [LogisticsController::class, 'updateStatus'])->name('status.update');
    Route::post('/issues/rts/{id}', [LogisticsManagementController::class,'rts'])->middleware('throttle:20,1')->name('rts');
    Route::get('/issues', [LogisticsManagementController::class, 'issues'])->name('issues');
    Route::post('/issues/recovery', [\App\Http\Controllers\LogisticsOperationsController::class,'receiveRecovery'])->middleware('throttle:20,1')->name('issues.recovery');
    Route::post('/issues', [LogisticsManagementController::class, 'storeIssue'])->name('issues.store');
    Route::patch('/issues/{id}', [LogisticsManagementController::class, 'updateIssue'])->name('issues.update');
    Route::get('/history', [LogisticsController::class, 'history'])->name('history');
    Route::get('/messages', [LogisticsController::class, 'messages'])->name('messages');
    Route::get('/messages/poll', [LogisticsController::class, 'messagesPoll'])->name('messages.poll');
    Route::post('/messages/report', [LogisticsController::class, 'reportMessage'])->name('messages.report');
    Route::post('/messages/send', [LogisticsController::class, 'messagesSend'])->name('messages.send');
    Route::get('/messages/{userId}', [LogisticsController::class, 'messagesThread'])->name('messages.thread');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::post('/account/update', [LogisticsController::class, 'accountUpdate'])->name('account.update');
    Route::post('/account/address', [LogisticsController::class, 'accountAddressUpdate'])->middleware('throttle:20,1,logistics-address-write:')->name('account.address');
    Route::get('/address-locations/{level}/{code?}', [LogisticsController::class, 'addressLocations'])->middleware('throttle:60,1,logistics-address:')->name('address.locations');
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
        Route::get('/reports/export', [LogisticsController::class, 'exportReport'])->name('reports.export');
        Route::get('/accounts', [LogisticsManagementController::class, 'accounts'])->name('accounts');
        Route::post('/accounts', [LogisticsManagementController::class, 'storeAccount'])->name('accounts.store');
        Route::post('/riders/accounts', [LogisticsManagementController::class, 'storeRiderAccount'])->name('riders.accounts.store');
        Route::post('/staff/accounts', [LogisticsManagementController::class, 'storeStaffAccount'])->name('staff.accounts.store');
        Route::get('/accounts/{id}/edit', [LogisticsManagementController::class, 'editAccount'])->name('accounts.edit');
        Route::patch('/accounts/{id}', [LogisticsManagementController::class, 'updateAccount'])->name('accounts.update');
        Route::get('/activity', [LogisticsManagementController::class, 'activity'])->name('activity');
        Route::get('/finance', [\App\Http\Controllers\LogisticsFinanceController::class, 'index'])->name('finance');
        Route::get('/finance/reconciliation', [\App\Http\Controllers\LogisticsReconciliationController::class, 'index'])->name('finance.reconciliation');
        Route::get('/finance/reconciliation/export', [\App\Http\Controllers\LogisticsReconciliationController::class, 'export'])->middleware('throttle:10,1')->name('finance.reconciliation.export');
        Route::get('/finance/riders', [\App\Http\Controllers\RiderPayoutController::class, 'index'])->name('finance.riders');
        Route::post('/finance/riders/funding', [\App\Http\Controllers\RiderPayoutController::class, 'funding'])->middleware('throttle:10,1')->name('finance.riders.funding');
        Route::post('/finance/riders/prepare', [\App\Http\Controllers\RiderPayoutController::class, 'prepare'])->middleware('throttle:10,1')->name('finance.riders.prepare');
        Route::post('/finance/riders/{id}/recognize', [\App\Http\Controllers\RiderPayoutController::class, 'recognize'])->middleware('throttle:20,1')->name('finance.riders.recognize');
        Route::post('/finance/riders/{id}/action', [\App\Http\Controllers\RiderPayoutController::class, 'action'])->middleware('throttle:20,1')->name('finance.riders.action');
        Route::post('/finance/riders/{id}/pay', [\App\Http\Controllers\RiderPayoutController::class, 'pay'])->middleware('throttle:10,1')->name('finance.riders.pay');
        Route::get('/finance/payouts', [\App\Http\Controllers\SellerPayoutController::class, 'index'])->name('finance.payouts');
        Route::post('/finance/payouts/prepare', [\App\Http\Controllers\SellerPayoutController::class, 'prepare'])->middleware('throttle:10,1')->name('finance.payouts.prepare');
        Route::post('/finance/payouts/{id}/action', [\App\Http\Controllers\SellerPayoutController::class, 'action'])->middleware('throttle:20,1')->name('finance.payouts.action');
        Route::post('/finance/payouts/{id}/pay', [\App\Http\Controllers\SellerPayoutController::class, 'pay'])->middleware('throttle:10,1')->name('finance.payouts.pay');
        Route::post('/finance/treasury/accounts', [\App\Http\Controllers\SellerPayoutController::class, 'treasuryAccount'])->middleware('throttle:10,1')->name('finance.treasury.account');
        Route::post('/finance/treasury/transfers', [\App\Http\Controllers\SellerPayoutController::class, 'transfer'])->middleware('throttle:10,1')->name('finance.treasury.transfer');
        Route::post('/finance/treasury/{id}/receipt', [\App\Http\Controllers\SellerPayoutController::class, 'receipt'])->middleware('throttle:10,1')->name('finance.treasury.receipt');
        Route::post('/finance/treasury/funding', [\App\Http\Controllers\SellerPayoutController::class, 'funding'])->middleware('throttle:10,1')->name('finance.treasury.funding');
        Route::get('/finance/staff/hubs/{hub}', [\App\Http\Controllers\StaffSalaryController::class,'hubHistory'])->name('finance.staff.hub-history');
        Route::post('/finance/staff/hub-salary', [\App\Http\Controllers\StaffSalaryController::class,'configureHub'])->middleware('throttle:20,1')->name('finance.staff.hub');
        Route::post('/finance/staff/use-hub', [\App\Http\Controllers\StaffSalaryController::class,'useHub'])->middleware('throttle:20,1')->name('finance.staff.use-hub');
        Route::get('/attendance', [\App\Http\Controllers\StaffAttendanceController::class,'index'])->name('attendance');
        Route::get('/attendance/export', [\App\Http\Controllers\StaffAttendanceController::class,'export'])->name('attendance.export');
        Route::post('/attendance/marks', [\App\Http\Controllers\StaffAttendanceController::class,'mark'])->middleware('throttle:20,1')->name('attendance.mark');
        Route::post('/attendance/corrections/{id}', [\App\Http\Controllers\StaffAttendanceController::class,'decide'])->middleware('throttle:20,1')->name('attendance.decide');
        Route::post('/attendance/records/{id}', [\App\Http\Controllers\StaffAttendanceController::class,'review'])->middleware('throttle:20,1')->name('attendance.review');
        Route::get('/attendance/records/{id}', [\App\Http\Controllers\StaffAttendanceController::class,'show'])->name('attendance.show');
        Route::get('/finance/staff/export', [\App\Http\Controllers\StaffSalaryController::class, 'export'])->name('finance.staff.export');
        Route::match(['get','post'], '/finance/staff/bulk/preview', [\App\Http\Controllers\StaffSalaryController::class, 'bulkPreview'])->middleware('throttle:20,1')->name('finance.staff.bulk.preview');
        Route::post('/finance/staff/bulk/execute', [\App\Http\Controllers\StaffSalaryController::class, 'bulkExecute'])->middleware('throttle:10,1')->name('finance.staff.bulk.execute');
        Route::get('/finance/staff/salaries/{staff}', [\App\Http\Controllers\StaffSalaryController::class, 'salaryHistory'])->name('finance.staff.history');
        Route::get('/finance/staff/payouts/{id}', [\App\Http\Controllers\StaffSalaryController::class, 'show'])->name('finance.staff.show');
        Route::get('/finance/staff', [\App\Http\Controllers\StaffSalaryController::class, 'index'])->name('finance.staff');
        Route::post('/finance/staff/configure', [\App\Http\Controllers\StaffSalaryController::class, 'configure'])->middleware('throttle:20,1')->name('finance.staff.configure');
        Route::post('/finance/staff/periods', [\App\Http\Controllers\StaffSalaryController::class, 'generatePeriod'])->middleware('throttle:20,1')->name('finance.staff.periods');
        Route::post('/finance/staff/funding', [\App\Http\Controllers\StaffSalaryController::class, 'funding'])->middleware('throttle:10,1')->name('finance.staff.funding');
        Route::post('/finance/staff/prepare', [\App\Http\Controllers\StaffSalaryController::class, 'prepare'])->middleware('throttle:10,1')->name('finance.staff.prepare');
        Route::post('/finance/staff/{id}/action', [\App\Http\Controllers\StaffSalaryController::class, 'action'])->middleware('throttle:20,1')->name('finance.staff.action');
        Route::post('/finance/staff/{id}/pay', [\App\Http\Controllers\StaffSalaryController::class, 'pay'])->middleware('throttle:10,1')->name('finance.staff.pay');
        Route::get('/finance/shipping/{id}', [\App\Http\Controllers\LogisticsShippingController::class, 'show'])->name('finance.shipping.show');
        Route::post('/finance/shipping/{id}/review', [\App\Http\Controllers\LogisticsShippingController::class, 'review'])->middleware('throttle:10,1')->name('finance.shipping.review');
        Route::post('/finance/shipping-account', [\App\Http\Controllers\LogisticsShippingController::class, 'shippingAccount'])->middleware('throttle:10,1')->name('finance.shipping.account');
        Route::post('/finance/shipping-rate', [\App\Http\Controllers\LogisticsShippingController::class, 'rate'])->middleware('throttle:10,1')->name('finance.shipping.rate');
        Route::post('/finance/settings', [\App\Http\Controllers\LogisticsFinanceController::class, 'settings'])->middleware('throttle:10,1')->name('finance.settings');
        Route::post('/hubs', [LogisticsManagementController::class, 'storeHub'])->middleware('throttle:20,1,logistics-address-write:')->name('hubs.store');
        Route::get('/hub-locations/{level}/{code?}', [LogisticsManagementController::class, 'hubLocations'])->middleware('throttle:60,1,logistics-hub-address:')->name('hubs.locations');
        Route::patch('/hubs/{id}', [LogisticsManagementController::class, 'updateHub'])->middleware('throttle:20,1,logistics-address-write:')->name('hubs.update');
        Route::patch('/vehicles/{id}', [LogisticsManagementController::class, 'updateVehicle'])->name('vehicles.update');
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

    // Hub Staff salary — own records and GCash receiving account.
    Route::get('/my-attendance', [\App\Http\Controllers\StaffAttendanceController::class,'mine'])->name('my-attendance');
    Route::post('/my-attendance/clock/{action}', [\App\Http\Controllers\StaffAttendanceController::class,'clock'])->whereIn('action',['in','out'])->middleware('throttle:20,1')->name('my-attendance.clock');
    Route::post('/my-attendance/corrections', [\App\Http\Controllers\StaffAttendanceController::class,'requestCorrection'])->middleware('throttle:20,1')->name('my-attendance.correction');
    Route::get('/my-attendance/records/{id}', [\App\Http\Controllers\StaffAttendanceController::class,'show'])->name('my-attendance.show');
    Route::get('/my-salary', [\App\Http\Controllers\StaffSalaryController::class, 'myIndex'])->name('my-salary');
    Route::post('/my-salary/destination', [\App\Http\Controllers\StaffSalaryController::class, 'destination'])->middleware('throttle:10,1')->name('my-salary.destination');
    Route::post('/my-salary/{id}/acknowledge', [\App\Http\Controllers\StaffSalaryController::class, 'acknowledge'])->middleware('throttle:10,1')->name('my-salary.acknowledge');

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
Route::prefix('rider')->name('rider.')->middleware(['rider', 'logistics.activity'])->group(function () {
    Route::get('/remittances', [\App\Http\Controllers\LogisticsCashDeskController::class, 'index'])->name('remittances');
    Route::post('/remittances/{id}/withdraw', [\App\Http\Controllers\LogisticsCashDeskController::class, 'withdraw'])->middleware('throttle:20,1')->name('remittances.withdraw');
    Route::post('/remittances', [\App\Http\Controllers\LogisticsCashDeskController::class, 'submit'])->middleware('throttle:20,1')->name('remittances.submit');
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
    Route::post('/deliveries/{id}/rts', [LogisticsManagementController::class,'rts'])->middleware('throttle:20,1')->name('rts');
    Route::get('/deliveries', [RiderController::class, 'deliveries'])->name('deliveries');
    Route::get('/deliveries/{id}', [RiderController::class, 'show'])->name('deliveries.show');
    Route::patch('/deliveries/{id}/advance', [RiderController::class, 'advance'])->name('deliveries.advance');
    Route::patch('/deliveries/{id}/failed', [RiderController::class, 'markFailed'])->name('deliveries.failed');
    Route::get('/history', [RiderController::class, 'history'])->name('history');
    Route::post('/earnings/destination', [\App\Http\Controllers\RiderPayoutController::class, 'destination'])->middleware('throttle:10,1')->name('earnings.destination');
    Route::post('/earnings/{id}/acknowledge', [\App\Http\Controllers\RiderPayoutController::class, 'acknowledge'])->middleware('throttle:10,1')->name('earnings.acknowledge');
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

// GCash endpoints: services enforce approved Buyer/Seller ownership independently of generic role middleware.
Route::get('/shipping-payments/{id}/proof', [\App\Http\Controllers\LogisticsShippingController::class, 'proof'])
    ->middleware(['web', 'auth', 'throttle:30,1'])->name('shipping.proof');
Route::get('/finance-evidence/{id}', [\App\Http\Controllers\SellerPayoutController::class, 'evidence'])
    ->middleware(['web','auth','throttle:30,1'])->name('finance.evidence');
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('gcash-purchases')->name('gcash.')->group(function () {
    Route::get('/', [GcashPurchaseController::class, 'index'])->name('index');
    Route::post('/cart-prices', [GcashPurchaseController::class, 'refreshPrices'])->name('refreshPrices');
    Route::post('/', [GcashPurchaseController::class, 'create'])->name('create');
    Route::get('/{id}', [GcashPurchaseController::class, 'show'])->name('show');
    foreach (['instructions', 'proof', 'reportPaid', 'decide', 'resolve', 'place', 'abandon', 'requestResolution'] as $action) {
        Route::post('/{id}/'.$action, [GcashPurchaseController::class, $action])->name($action);
    }
    Route::get('/{id}/proofs/{proof}', [GcashPurchaseController::class, 'receipt'])->name('receipt');
});
