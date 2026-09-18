<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\AccountingAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sso', [SsoController::class, 'login'])->name('sso.login');

Route::get('/accounting/verify', [AccountingAuthController::class, 'showVerify'])
            ->name('accounting.verify');
Route::post('/accounting/verify-code', [AccountingAuthController::class, 'verifyCode'])
            ->name('accounting.verify.code');

Route::middleware('accounting.auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('accounting.dashboard');
    })->name('accounting.dashboard');
});