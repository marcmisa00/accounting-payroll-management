<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AccountingLoginController;
use App\Http\Controllers\AccountingAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\EditPayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\TimeAdjustmentController;
use App\Http\Controllers\OvertimeApplicationController;
use App\Http\Controllers\EeoApplicationController;
use App\Http\Controllers\HrRosterController;
use App\Http\Controllers\LeaveCreditController;
use App\Http\Controllers\HrTrackerController;
use App\Http\Controllers\HsoApplicationController;
use App\Http\Controllers\TransferApplicationController;
use App\Http\Controllers\BenefitsController;

Route::redirect('/', '/login');

Route::get('/login', [AccountingLoginController::class, 'showLogin'])->name('login');
Route::post('/login', [AccountingLoginController::class, 'login'])->middleware('throttle:accounting-auth')->name('login.submit');
Route::get('/register', [AccountingLoginController::class, 'showRegister'])->name('register');
Route::post('/register', [AccountingLoginController::class, 'register'])->middleware('throttle:accounting-auth')->name('register.submit');
Route::get('/register/setup', [AccountingLoginController::class, 'showSetup'])->name('register.setup');
Route::post('/register/setup', [AccountingLoginController::class, 'confirmSetup'])->middleware('throttle:accounting-auth')->name('register.setup.confirm');
Route::get('/accounting/verify', [AccountingAuthController::class, 'showVerify'])->name('accounting.verify');
Route::post('/accounting/verify-code', [AccountingAuthController::class, 'verifyCode'])->middleware('throttle:accounting-auth')->name('accounting.verify.code');
Route::middleware('accounting.auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/detail', [DashboardController::class, 'detail'])->name('dashboard.detail');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/leaves/applications', [LeaveApplicationController::class, 'index'])->name('leaves.applications');
    Route::get('/leaves/time-adjustments', [TimeAdjustmentController::class, 'index'])->name('leaves.time-adjustments');
    Route::get('/leaves/overtime', [OvertimeApplicationController::class, 'index'])->name('leaves.overtime');
    Route::get('/leaves/eeo', [EeoApplicationController::class, 'index'])->name('leaves.eeo');
    Route::get('/hr/roster', [HrRosterController::class, 'index'])->name('hr.roster');
    Route::get('/hr/roster/{idno}', [HrRosterController::class, 'show'])->where('idno', '[A-Za-z0-9-]+')->name('hr.roster.show');
    Route::get('/hr/leave-credits', [LeaveCreditController::class, 'index'])->name('hr.leave-credits');
    Route::get('/hr/leave-credits/{idno}/used', [LeaveCreditController::class, 'usedDates'])->where('idno', '[A-Za-z0-9-]+')->name('hr.leave-credits.used');
    Route::get('/hr/monthly-leave-credits', [LeaveCreditController::class, 'monthly'])->name('hr.monthly-leave-credits');
    Route::get('/hr/movement-tracker', [HrTrackerController::class, 'movement'])->name('hr.movement-tracker');
    Route::get('/hr/work-setup-tracker', [HrTrackerController::class, 'workSetup'])->name('hr.work-setup-tracker');
    Route::get('/hr/resigned', [HrTrackerController::class, 'resigned'])->name('hr.resigned');
    Route::get('/hr/resigned/{idno}', [HrTrackerController::class, 'resignedShow'])->where('idno', '[A-Za-z0-9-]+')->name('hr.resigned.show');
    Route::get('/hso-applications', [HsoApplicationController::class, 'index'])->name('hso.index');
    Route::get('/hso-applications/{id}', [HsoApplicationController::class, 'show'])->whereNumber('id')->name('hso.show');
    Route::get('/hso-applications/{id}/agreement', [HsoApplicationController::class, 'agreement'])->whereNumber('id')->name('hso.agreement');
    Route::post('/hso-applications/{id}/acknowledge', [HsoApplicationController::class, 'acknowledgeApplication'])->whereNumber('id')->name('hso.acknowledge');
    Route::get('/transfer-applications', [TransferApplicationController::class, 'index'])->name('transfer.index');
    Route::post('/transfer-applications/{id}/acknowledge', [TransferApplicationController::class, 'acknowledgeApplication'])->whereNumber('id')->name('transfer.acknowledge');
    Route::post('/logout', [AccountingAuthController::class, 'logout'])->name('logout');

    Route::prefix('holidays')->name('holidays.')->group(function () {
        Route::get('/', [HolidayController::class, 'index'])->name('index');
        Route::get('/events', [HolidayController::class, 'events'])->name('events');
        Route::post('/', [HolidayController::class, 'store'])->name('store');
        Route::put('/{holiday}', [HolidayController::class, 'update'])->name('update');
        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])->name('destroy');
    });
    
Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('create', [PayrollController::class, 'create'])->name('create');
        Route::post('create', [PayrollController::class, 'store']) ->name('store');
        Route::get('manage', [PayrollController::class, 'manageSelect'])->name('manage');
        Route::post('manage', [PayrollController::class, 'manageSelectSubmit'])->name('manageSelectSubmit');    
        Route::get('manage/{payroll}', [PayrollController::class, 'show'])->name('show');
        Route::post('manage/{payroll}/post', [PayrollController::class, 'postPayslip'])->name('postPayslip');
        Route::post('manage/{payroll}/undo', [PayrollController::class, 'undoPostPayslip'])->name('undoPostPayslip');
        Route::get('benefits', [BenefitsController::class, 'index'])->name('benefits.index');
        Route::get('benefits/history', [BenefitsController::class, 'history'])->name('benefits.history');
        Route::get('benefits/{idno}', [BenefitsController::class, 'edit'])->where('idno', '[A-Za-z0-9-]+')->name('benefits.edit');
        Route::post('benefits/{idno}', [BenefitsController::class, 'update'])->where('idno', '[A-Za-z0-9-]+')->name('benefits.update');
        Route::get('history', [EditPayrollController::class, 'historyIndex'])->name('history.index');
        Route::post('history', [EditPayrollController::class, 'historySelect'])->name('history.select');
        Route::get('manage/{payroll}/history', [EditPayrollController::class, 'history'])->name('history');
        Route::get('payslip/rated/{detail}', [PayslipController::class, 'rated'])->whereNumber('detail')->name('payslip.rated');
        Route::get('payslip/rated/{detail}/pdf', [PayslipController::class, 'ratedPdf'])->whereNumber('detail')->name('payslip.rated.pdf');
        Route::get('payslip/fixed/{detail}', [PayslipController::class, 'fixed'])->whereNumber('detail')->name('payslip.fixed');
        Route::get('payslip/fixed/{detail}/pdf', [PayslipController::class, 'fixedPdf'])->whereNumber('detail')->name('payslip.fixed.pdf');
        Route::get('payslip/daily/{detail}', [PayslipController::class, 'daily'])->whereNumber('detail')->name('payslip.daily');
        Route::get('payslip/daily/{detail}/pdf', [PayslipController::class, 'dailyPdf'])->whereNumber('detail')->name('payslip.daily.pdf');
        Route::prefix('manage/{payroll}/edit/{idno}') ->name('edit.') 
            ->group(function () {
                Route::get('/', [EditPayrollController::class, 'show'])->name('show');
                Route::get('payslip/rated/{detail}', [PayslipController::class, 'ratedFromEdit'])->whereNumber('detail')->name('payslip.rated');
                Route::get('payslip/fixed/{detail}', [PayslipController::class, 'fixedFromEdit'])->whereNumber('detail')->name('payslip.fixed');
                Route::get('payslip/daily/{detail}', [PayslipController::class, 'dailyFromEdit'])->whereNumber('detail')->name('payslip.daily');
                Route::post('save', [EditPayrollController::class, 'save'])->name('save');
                Route::get('time/{attendance}/edit', [EditPayrollController::class, 'editTime'])->name('time.edit');
                Route::post('time/{attendance}/override', [EditPayrollController::class, 'updateTimeOverride'])->name('time.override');
                Route::delete('time/{attendance}/override', [EditPayrollController::class, 'clearTimeOverride'])->name('time.override.clear');
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
                    Route::delete('constant/{employeeAddon}', [EditPayrollController::class, 'destroyConstantAddon']) ->name('constant.destroy');
                    Route::delete('{payrollAddon}', [EditPayrollController::class, 'destroyAddon'])->name('destroy');
                    Route::post('generate', [EditPayrollController::class, 'generateAddons'])->name('generate');  
                });
            });
        });
});
