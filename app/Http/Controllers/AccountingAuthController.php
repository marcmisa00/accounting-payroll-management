<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingAuthController extends Controller
{
    public function showVerify(Request $request)
    {
        if (!session('portal_authenticated') || !session('portal_idno')) {
            abort(403, 'Portal authentication required.');
        }

        $idno = session('portal_idno');

        $employee = DB::connection('hris')
            ->table('employee_payroll')
            ->where('idno', $idno)
            ->select('idno', 'ga_secret')
            ->first();

        if (!$employee) {
            abort(403, 'Employee record not found.');
        }

        return view('accounting.verify', [
            'idno' => $idno,
            'has2fa' => !empty($employee->ga_secret),
        ]);
    }

    public function verifyCode(Request $request)
    {
        // Make sure the user came from the Portal
        if (!session('portal_authenticated') || !session('portal_idno')) {
            abort(403, 'Portal authentication required.');
        }

        // Validate the submitted code
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $idno = session('portal_idno');

        // Get the existing Google Authenticator secret from HRIS
        $employee = DB::connection('hris')
            ->table('employee_payroll')
            ->where('idno', $idno)
            ->select('idno', 'ga_secret')
            ->first();

        if (!$employee || empty($employee->ga_secret)) {
            return redirect()
                ->route('accounting.verify')
                ->with('error', 'Google Authenticator is not registered for this account.');
        }

        // Google Authenticator
        $googleAuthenticator = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();

        // Verify the 6-digit code
        if (!$googleAuthenticator->checkCode($employee->ga_secret, $request->code)) {
            return redirect()
                ->route('accounting.verify')
                ->with('error', 'Invalid Google Authenticator code.');
        }

       // Google Authenticator successfully verified
        session([
            'accounting_authenticated' => true,
            'accounting_authenticated_at' => time(),
        ]);

        return redirect()->route('dashboard');
    }    
}