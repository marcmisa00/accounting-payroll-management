<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthenticatorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AccountingAuthController extends Controller
{
    // Seconds allowed between the password step and the OTP step
    public const PENDING_SECONDS = 5 * 60;

    // Wrong codes per account before a lockout (from any IP)
    private const OTP_MAX_FAILS = 5;

    private const LOCK_SECONDS = 15 * 60;

    private const EXPIRED = 'Your sign-in expired. Please enter your password again.';

    public function showVerify(Request $request)
    {
        // Must have passed the ID number + password step first
        $user = $this->pendingUser();

        if (!$user) {
            // pendingUser() flashes the expiry message when that was the reason
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
            // pendingUser() flashes the expiry message when that was the reason
            return redirect()->route('login');
        }

        $limitKey = 'accounting-otp:' . $user->id;

        if (RateLimiter::tooManyAttempts($limitKey, self::OTP_MAX_FAILS)) {
            return $this->lockedOut($limitKey);
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

        // Verify the 6-digit code; a code already used to sign in is refused
        if (!AuthenticatorCode::verify($user->ga_secret, $request->code, 'user:' . $user->id)) {
            RateLimiter::hit($limitKey, self::LOCK_SECONDS);

            if (RateLimiter::tooManyAttempts($limitKey, self::OTP_MAX_FAILS)) {
                return $this->lockedOut($limitKey);
            }

            return redirect()
                ->route('accounting.verify')
                ->with('error', 'Invalid Google Authenticator code.');
        }

        // Google Authenticator successfully verified
        RateLimiter::clear($limitKey);
        $request->session()->regenerate();
        session()->forget(['accounting_pending_user_id', 'accounting_pending_at']);

        // portal_idno is kept as the session key for the signed-in ID (audit fields, layout greeting).
        // AccountingAuth uses the two timestamps for the session age and the HRIS access re-check.
        session([
            'portal_idno' => $user->idno,
            'accounting_authenticated' => true,
            'accounting_authenticated_at' => time(),
            'accounting_checked_at' => time(),
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

        if (!$userId) {
            return null;
        }

        if (time() - (int) session('accounting_pending_at', 0) > self::PENDING_SECONDS) {
            session()->forget(['accounting_pending_user_id', 'accounting_pending_at']);
            session()->flash('error', self::EXPIRED);

            return null;
        }

        return User::find($userId);
    }

    // Too many wrong codes: drop the half-finished sign-in so the password is needed again
    private function lockedOut(string $limitKey)
    {
        session()->forget(['accounting_pending_user_id', 'accounting_pending_at']);
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($limitKey) / 60));

        return redirect()
            ->route('login')
            ->with('error', "Too many wrong codes. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
    }
}
