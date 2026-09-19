<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\AccountingAuthController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sso', [SsoController::class, 'login'])->name('sso.login');

Route::get('/accounting/verify', [AccountingAuthController::class, 'showVerify'])
    ->name('accounting.verify');
Route::post('/accounting/verify-code', [AccountingAuthController::class, 'verifyCode'])
    ->name('accounting.verify.code');

Route::middleware('accounting.auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/detail', [DashboardController::class, 'detail'])->name('dashboard.detail');
    Route::post('/logout', [AccountingAuthController::class, 'logout'])->name('logout');
});