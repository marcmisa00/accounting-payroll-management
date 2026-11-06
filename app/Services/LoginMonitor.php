<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sign-in monitoring for the NESI Admin site: writes to admin-nesi (login_sessions,
 * login_failures) over the `admin` connection. Passwords and codes are never written.
 *
 * Monitoring must never block Accounting: every write is wrapped, and a failure is
 * only logged (without personal details).
 */
class LoginMonitor
{
    private const APP = 'accounting';

    // last_seen_at is written at most this often per session
    private const TOUCH_SECONDS = 60;

    private const SESSION_KEY = 'login_monitor_id';

    private const SEEN_KEY = 'login_monitor_seen';

    /** After the OTP succeeds (and after the session id is regenerated) */
    public static function start(Request $request, User $user): void
    {
        self::safely(function () use ($request, $user) {
            $now = self::now();
            $id = DB::connection('admin')->table('login_sessions')->insertGetId([
                'app' => self::APP,
                'idno' => (string) $user->idno,
                'username' => (string) $user->username,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'login_at' => $now,
                'last_seen_at' => $now,
            ]);

            session([self::SESSION_KEY => $id, self::SEEN_KEY => time()]);
        });
    }

    /** On every signed-in request; writes at most once a minute */
    public static function touch(): void
    {
        $id = session(self::SESSION_KEY);

        if (!$id || time() - (int) session(self::SEEN_KEY, 0) < self::TOUCH_SECONDS) {
            return;
        }

        // Set first, so a failing database is not retried on every request
        session([self::SEEN_KEY => time()]);

        self::safely(fn () => DB::connection('admin')->table('login_sessions')
            ->where('id', $id)->whereNull('ended_at')
            ->update(['last_seen_at' => self::now()]));
    }

    /** Before the session is invalidated: logout, expired, no_access, disabled, reset */
    public static function end(string $reason): void
    {
        $id = session(self::SESSION_KEY);

        if (!$id) {
            return;
        }

        self::safely(function () use ($id, $reason) {
            $now = self::now();
            DB::connection('admin')->table('login_sessions')
                ->where('id', $id)->whereNull('ended_at')
                ->update(['last_seen_at' => $now, 'ended_at' => $now, 'end_reason' => $reason]);
        });
    }

    /** A refused sign-in: unknown_user, wrong_password, wrong_code, locked, no_access, disabled */
    public static function failure(Request $request, string $loginName, ?string $idno, string $reason): void
    {
        self::safely(fn () => DB::connection('admin')->table('login_failures')->insert([
            'app' => self::APP,
            'login_name' => mb_substr(trim($loginName), 0, 50),
            'idno' => $idno !== null && $idno !== '' ? $idno : null,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'created_at' => self::now(),
        ]));
    }

    private static function now(): string
    {
        return CarbonImmutable::now('Asia/Manila')->format('Y-m-d H:i:s');
    }

    private static function safely(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $e) {
            Log::warning('Login monitor write failed: ' . get_class($e));
        }
    }
}
