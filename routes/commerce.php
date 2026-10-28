<?php

use App\Http\Controllers\CommerceController;
use Illuminate\Support\Facades\Route;

Route::post('/account/notifications/read-all', [CommerceController::class, 'notificationsReadAll'])->middleware(['web', 'auth'])->name('account.notifications.read-all');
Route::get('/account/notifications/{id}', [CommerceController::class, 'notificationOpen'])->middleware(['web', 'auth'])->name('account.notifications.open');

Route::middleware(['web', 'auth'])->prefix('account/security')->name('account.')->group(function () {
    Route::post('/email/code', [CommerceController::class, 'emailCode'])->middleware('throttle:3,5')->name('email.code');
    Route::post('/email/verify', [CommerceController::class, 'emailVerify'])->middleware('throttle:6,1')->name('email.verify');
});

Route::get('/buyer/wishlist', [CommerceController::class, 'wishlistPage'])->middleware(['web', 'buyer'])->name('buyer.wishlist');
Route::patch('/buyer/addresses/{address}/complete', [CommerceController::class, 'completeAddress'])->middleware(['web', 'buyer'])->name('buyer.addresses.complete');

Route::prefix('buyer/commerce')->name('buyer.commerce.')->middleware(['web', 'buyer'])->group(function () {
    Route::get('/', [CommerceController::class, 'index'])->name('index');
    Route::post('/wishlist/{product}', [CommerceController::class, 'wishlist'])->name('wishlist');
    Route::post('/vouchers/{voucher}/claim', [CommerceController::class, 'claim'])->name('claim');
    Route::post('/flash/{sale}/reminder', [CommerceController::class, 'reminder'])->name('reminder');
    Route::post('/notifications/read-all', [CommerceController::class, 'markAll'])->name('read-all');
    Route::post('/email/code', [CommerceController::class, 'emailCode'])->middleware('throttle:3,5')->name('email.code');
    Route::post('/email/verify', [CommerceController::class, 'emailVerify'])->middleware('throttle:6,1')->name('email.verify');
    Route::get('/checkout', [CommerceController::class, 'checkoutPage'])->name('checkout');
    Route::post('/quote', [CommerceController::class, 'quote'])->middleware('throttle:60,1')->name('quote');
    Route::post('/checkout', [CommerceController::class, 'checkout'])->middleware('throttle:15,1')->name('place');
    Route::post('/orders/{order}/reviews', [CommerceController::class, 'review'])->name('review');
    Route::post('/orders/{order}/reviews/{review}', [CommerceController::class, 'review'])->name('review.edit');
    Route::post('/orders/{order}/returns', [CommerceController::class, 'requestReturn'])->name('return');
});
foreach (['seller', 'admin'] as $role) {
    Route::prefix($role.'/commerce')->name($role.'.commerce.')->middleware(['web', $role])->group(function () {
        Route::get('/', [CommerceController::class, 'management'])->name('index');
        Route::post('/vacation', [CommerceController::class, 'vacation'])->name('vacation');
        Route::post('/shipping', [CommerceController::class, 'rate'])->name('shipping');
        Route::post('/vouchers', [CommerceController::class, 'voucher'])->name('voucher');
        Route::post('/flash-sales', [CommerceController::class, 'flashSale'])->name('flash');
        Route::post('/promotions/{kind}/{id}/end', [CommerceController::class, 'endPromotion'])->name('end');
    });
}
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/commerce/returns/{id}', [CommerceController::class, 'returnAction'])->name('commerce.return.action');
    Route::get('/commerce/returns/{id}/photos/{index}', [CommerceController::class, 'evidence'])->whereNumber('index')->name('commerce.return.photo');
});
