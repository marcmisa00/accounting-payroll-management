<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\AccountingAuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sso-test', [SsoController::class, 'test']);
Route::get('/accounting/verify', [AccountingAuthController::class, 'showVerify'])
            ->name('accounting.verify');
Route::get('/sso', [SsoController::class, 'login']);
Route::post('/accounting/verify-code', [AccountingAuthController::class, 'verifyCode'])
            ->name('accounting.verify.code');
