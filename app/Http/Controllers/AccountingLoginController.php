<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountingAccess;
use App\Services\AuthenticatorCode;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

class AccountingLoginController extends Controller
{
    private const NO_ACCESS = "You don't have access to this page.";

    // One message for every failed registration check, so it can't be used to learn which IDs exist
    private const NOT_VERIFIED = "We couldn't verify these details. Check your ID number and HRIS authenticator code.";

    // Wrong passwords per username, and wrong HRIS codes per ID number, before a lockout (from any IP)
    private const LOGIN_MAX_FAILS = 10;

    private const REGISTER_MAX_FAILS = 5;

    private const LOCK_SECONDS = 15 * 60;

    // Minutes a pending registration (password + new secret) stays valid in the session
    private const PENDING_MINUTES = 10;

    // "Remember me" only pre-fills the username; it never skips the password or OTP
    private const REMEMBER_COOKIE = 'accounting_username';

    private const REMEMBER_MINUTES = 60 * 24 * 30;

    private const USERNAME_RULES = ['required', 'string', 'min:4', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'];

    public function showLogin(Request $request)
    {
        if (session('accounting_authenticated')) {
            return redirect()->route('dashboard');
        }

        return view('accounting.login', [
            'rememberedUsername' => (string) $request->cookie(self::REMEMBER_COOKIE, ''),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ]);

        $username = strtolower(trim($data['username']));

        // Counted for unknown usernames too, so a lockout doesn't reveal which ones exist
        $limitKey = 'accounting-login:' . sha1($username);

        if (RateLimiter::tooManyAttempts($limitKey, self::LOGIN_MAX_FAILS)) {
            return back()
                ->withInput($request->only('username', 'remember'))
                ->with('error', $this->lockedMessage($limitKey));
        }

        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($limitKey, self::LOCK_SECONDS);

            return back()
                ->withInput($request->only('username', 'remember'))
                ->with('error', 'Invalid username or password.');
        }

        RateLimiter::clear($limitKey);

        // Access is managed in HRIS, so re-check it on every login
        if (!AccountingAccess::allows($user->idno)) {
            return back()
                ->withInput($request->only('username', 'remember'))
                ->with('error', self::NO_ACCESS);
        }

        $request->session()->regenerate();
        // The OTP step must follow within AccountingAuthController::PENDING_SECONDS
        session([
            'accounting_pending_user_id' => $user->id,
            'accounting_pending_at' => time(),
        ]);

        $rememberCookie = $request->boolean('remember')
            ? cookie(self::REMEMBER_COOKIE, $user->username, self::REMEMBER_MINUTES)
            : cookie()->forget(self::REMEMBER_COOKIE);

        return redirect()->route('accounting.verify')->withCookie($rememberCookie);
    }

    public function showRegister()
    {
        if (session('accounting_authenticated')) {
            return redirect()->route('dashboard');
        }

        return view('accounting.register');
    }

    public function register(Request $request)
    {
        $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);

        $data = $request->validate([
            'idno' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'hris_code' => ['required', 'digits:6'],
            'username' => self::USERNAME_RULES,
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'idno.regex' => 'ID number may only contain letters, numbers and dashes.',
            'hris_code.required' => 'Enter the 6-digit code from your HRIS Accounting authenticator.',
            'hris_code.digits' => 'The HRIS authenticator code must be 6 digits.',
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes and underscores.',
        ]);

        // Knowing an ID number is not enough: the person must also hold the authenticator
        // they already use for Accounting in HRIS (hris.employee_payroll.ga_secret).
        $limitKey = 'accounting-register:' . sha1(strtoupper($data['idno']));

        if (RateLimiter::tooManyAttempts($limitKey, self::REGISTER_MAX_FAILS)) {
            return back()
                ->withInput($request->only('idno', 'username'))
                ->with('error', $this->lockedMessage($limitKey));
        }

        $hrisSecret = (string) DB::connection('hris')
            ->table('employee_payroll')
            ->where('idno', $data['idno'])
            ->value('ga_secret');

        $verified = AuthenticatorCode::verify(trim($hrisSecret), $data['hris_code'], 'hris:' . strtoupper($data['idno']))
            && AccountingAccess::allows($data['idno'])
            && !User::where('idno', $data['idno'])->exists();

        if (!$verified) {
            RateLimiter::hit($limitKey, self::LOCK_SECONDS);

            return back()
                ->withInput($request->only('idno', 'username'))
                ->with('error', self::NOT_VERIFIED);
        }

        RateLimiter::clear($limitKey);

        if (User::where('username', $data['username'])->exists()) {
            return back()
                ->withInput($request->only('idno', 'username'))
                ->with('error', 'This username is already taken.');
        }

        // Nothing is saved until the authenticator code is confirmed
        session(['accounting_registration' => [
            'idno' => $data['idno'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'secret' => Crypt::encryptString((new GoogleAuthenticator())->generateSecret()),
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->timestamp,
        ]]);

        return redirect()->route('register.setup');
    }

    public function showSetup()
    {
        $pending = $this->pendingRegistration();

        if (!$pending) {
            return redirect()
                ->route('register')
                ->with('error', 'Your registration session expired. Please start again.');
        }

        $secret = Crypt::decryptString($pending['secret']);

        $otpauth = 'otpauth://totp/'
            . rawurlencode('NESI Accounting:' . $pending['username'])
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode('NESI Accounting');

        // Rendered locally so the secret never goes to an outside QR service
        $qrSvg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd())))
            ->writeString($otpauth);

        return view('accounting.setup-2fa', [
            'username' => $pending['username'],
            'qrSvg' => $qrSvg,
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirmSetup(Request $request)
    {
        $pending = $this->pendingRegistration();

        if (!$pending) {
            return redirect()
                ->route('register')
                ->with('error', 'Your registration session expired. Please start again.');
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $secret = Crypt::decryptString($pending['secret']);

        if (!AuthenticatorCode::verify($secret, $request->code, 'setup:' . $pending['username'])) {
            return redirect()
                ->route('register.setup')
                ->with('error', 'Invalid Google Authenticator code.');
        }

        // Re-check in case HRIS access changed while setting up
        if (!AccountingAccess::allows($pending['idno'])) {
            session()->forget('accounting_registration');

            return redirect()->route('register')->with('error', self::NO_ACCESS);
        }

        $profile = DB::connection('hris')
            ->table('employee_profile')
            ->where('idno', $pending['idno'])
            ->select('firstname', 'lastname')
            ->first();

        $email = DB::connection('hris')
            ->table('employee_payroll')
            ->where('idno', $pending['idno'])
            ->value('email');

        $user = new User();
        $user->forceFill([
            'idno' => $pending['idno'],
            'username' => $pending['username'],
            'name' => $profile ? trim($profile->firstname . ' ' . $profile->lastname) : $pending['idno'],
            'email' => filled($email) && !User::where('email', $email)->exists() ? $email : null,
            'password' => $pending['password'],
            'ga_secret' => $secret,
        ]);

        try {
            // password is already hashed; the hashed cast leaves bcrypt values as-is
            $user->save();
        } catch (UniqueConstraintViolationException $e) {
            session()->forget('accounting_registration');

            return redirect()
                ->route('login')
                ->with('error', 'This ID number or username is already registered. Please sign in.');
        }

        session()->forget('accounting_registration');

        return redirect()
            ->route('login')
            ->withInput(['username' => $user->username])
            ->with('success', 'Registration complete. You can now sign in.');
    }

    private function pendingRegistration(): ?array
    {
        $pending = session('accounting_registration');

        if (!is_array($pending) || empty($pending['username']) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget('accounting_registration');

            return null;
        }

        return $pending;
    }

    private function lockedMessage(string $limitKey): string
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($limitKey) / 60));

        return "Too many attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.';
    }
}
