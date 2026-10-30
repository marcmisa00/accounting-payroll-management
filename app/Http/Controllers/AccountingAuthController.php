<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AccountingAuthController extends Controller
{
    public function showVerify(Request $request)
    {
        // Must have passed the ID number + password step first
        $user = $this->pendingUser();

        if (!$user) {
            return redirect()->route('login');
        }

        return view('accounting.verify', [
            'idno' => $user->idno,
            'has2fa' => !empty($user->ga_secret),
        ]);
    }

    public function verifyCode(Request $request)
    {
        $user = $this->pendingUser();

        if (!$user) {
            return redirect()->route('login');
        }

        // Validate the submitted code
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (empty($user->ga_secret)) {
            return redirect()
                ->route('accounting.verify')
                ->with('error', 'Google Authenticator is not registered for this account.');
        }

        // Google Authenticator
        $googleAuthenticator = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();

        // Verify the 6-digit code
        if (!$googleAuthenticator->checkCode($user->ga_secret, $request->code)) {
            return redirect()
                ->route('accounting.verify')
                ->with('error', 'Invalid Google Authenticator code.');
        }

        // Google Authenticator successfully verified
        $request->session()->regenerate();
        session()->forget('accounting_pending_user_id');

        // portal_idno is kept as the session key for the signed-in ID (audit fields, layout greeting)
        session([
            'portal_idno' => $user->idno,
            'accounting_authenticated' => true,
            'accounting_authenticated_at' => time(),
        ]);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(): ?User
    {
        $userId = session('accounting_pending_user_id');

        return $userId ? User::find($userId) : null;
    }
}
