<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AccountingAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountingAuth
{
    // HRIS access (ACCOUNTING, not resigned) is re-checked this often during a session
    private const RECHECK_SECONDS = 5 * 60;

    // A session ends this long after the OTP, even while it is in use
    private const MAX_SESSION_SECONDS = 12 * 60 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (!session('accounting_authenticated')) {
            return redirect()->route('login');
        }

        $now = time();

        if ($now - (int) session('accounting_authenticated_at', 0) > self::MAX_SESSION_SECONDS) {
            return $this->signOut($request, 'Your session expired. Please sign in again.');
        }

        if ($now - (int) session('accounting_checked_at', 0) >= self::RECHECK_SECONDS) {
            $idno = (string) session('portal_idno');

            if ($idno === '' || !User::where('idno', $idno)->exists() || !AccountingAccess::allows($idno)) {
                return $this->signOut($request, "You don't have access to Accounting anymore. Please contact HR if this is a mistake.");
            }

            session(['accounting_checked_at' => $now]);
        }

        return $next($request);
    }

    private function signOut(Request $request, string $message): Response
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', $message);
    }
}
