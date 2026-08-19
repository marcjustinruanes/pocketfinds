<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LogisticsController;

Route::get('/', fn() => view('guest.home'));

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
    Route::get('/password/reset', fn() => view('auth.login'))->name('password.request');
    Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('google.callback');
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
