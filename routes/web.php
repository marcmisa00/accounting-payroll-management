<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\AccountingAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\EditPayrollController;

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

    Route::prefix('payroll')->name('payroll.')->group(function () {
    Route::get('create', [PayrollController::class, 'create'])->name('create');
    Route::post('create', [PayrollController::class, 'store'])->name('store');
 
    Route::get('manage', [PayrollController::class, 'manageSelect'])->name('manage');
    Route::post('manage', [PayrollController::class, 'manageSelectSubmit'])->name('manageSelectSubmit');
 
    Route::get('manage/{payroll}', [PayrollController::class, 'show'])->name('show');
    Route::post('manage/{payroll}/post', [PayrollController::class, 'postPayslip'])->name('postPayslip');
    Route::post('manage/{payroll}/undo', [PayrollController::class, 'undoPostPayslip'])->name('undoPostPayslip');

    Route::prefix('manage/{payroll}/edit/{idno}')->name('edit.')->group(function () {
            Route::get('/', [EditPayrollController::class, 'show'])->name('show');
            Route::post('save', [EditPayrollController::class, 'save'])->name('save');
            Route::delete('time/{attendance}', [EditPayrollController::class, 'deleteTime'])->name('time.destroy');
 
        Route::prefix('deductions')->name('deductions.')->group(function () {
            Route::post('/', [EditPayrollController::class, 'storeDeduction'])->name('store');
            Route::post('constant', [EditPayrollController::class, 'addConstantDeduction'])->name('constant.store');
            Route::post('constant/{employeeDeduction}/apply', [EditPayrollController::class, 'applyConstantDeduction'])->name('constant.apply');
            Route::delete('constant/{employeeDeduction}', [EditPayrollController::class, 'destroyConstantDeduction'])->name('constant.destroy');
            Route::delete('{payrollDeduction}', [EditPayrollController::class, 'destroyDeduction'])->name('destroy');
            Route::post('generate', [EditPayrollController::class, 'generateDeductions'])->name('generate');
        });
 
        Route::prefix('addons')->name('addons.')->group(function () {
            Route::post('/', [EditPayrollController::class, 'storeAddon'])->name('store');
            Route::post('constant', [EditPayrollController::class, 'addConstantAddon'])->name('constant.store');
            Route::post('constant/{employeeAddon}/apply', [EditPayrollController::class, 'applyConstantAddon'])->name('constant.apply');
            Route::delete('constant/{employeeAddon}', [EditPayrollController::class, 'destroyConstantAddon'])->name('constant.destroy');
            Route::delete('{payrollAddon}', [EditPayrollController::class, 'destroyAddon'])->name('destroy');
            Route::post('generate', [EditPayrollController::class, 'generateAddons'])->name('generate');
        });
    });

});
});
