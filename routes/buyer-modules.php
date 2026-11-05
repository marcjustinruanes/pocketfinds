<?php
use App\Http\Controllers\BuyerModulesController as Modules;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth'])->prefix('account')->name('account.')->group(function(){
    Route::post('/photo',[Modules::class,'photo'])->middleware('throttle:10,1')->name('photo');
    Route::get('/cancellation-refunds',[Modules::class,'refunds'])->name('cancellation-refunds');
    Route::post('/cancellation-refunds/{refund}',[Modules::class,'completeRefund'])->middleware('throttle:10,1')->name('cancellation-refunds.complete');
});
Route::middleware(['web','buyer'])->prefix('buyer')->name('buyer.')->group(function(){
    Route::patch('/addresses/{address}',[Modules::class,'address'])->name('addresses.update');
    Route::post('/addresses/{address}/default',[Modules::class,'defaultAddress'])->name('addresses.default');
    Route::get('/following',[Modules::class,'following'])->name('following');
});
Route::middleware(['web','seller'])->prefix('seller/shop-categories')->name('seller.shop-categories.')->group(function(){
    Route::get('/',[Modules::class,'categories'])->name('index');
    Route::post('/',[Modules::class,'saveCategory'])->name('save');
    Route::delete('/{category}',[Modules::class,'deleteCategory'])->name('delete');
    Route::post('/products/{product}',[Modules::class,'assignCategories'])->name('assign');
});
