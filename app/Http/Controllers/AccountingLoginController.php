<?php

namespace App\Http\Controllers;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

class AccountingLoginController extends Controller
{
    private const NO_ACCESS = "You don't have access to this page.";

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

        $user = User::where('username', strtolower(trim($data['username'])))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return back()
                ->withInput($request->only('username', 'remember'))
                ->with('error', 'Invalid username or password.');
        }

        // Access is managed in HRIS, so re-check it on every login
        if (!$this->hasAccountingAccess($user->idno)) {
            return back()
                ->withInput($request->only('username', 'remember'))
                ->with('error', self::NO_ACCESS);
        }

        $request->session()->regenerate();
        session(['accounting_pending_user_id' => $user->id]);

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
            'username' => self::USERNAME_RULES,
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'idno.regex' => 'ID number may only contain letters, numbers and dashes.',
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes and underscores.',
        ]);

        if (!$this->hasAccountingAccess($data['idno'])) {
            return back()
                ->withInput($request->only('idno', 'username'))
                ->with('error', self::NO_ACCESS);
        }

        if (User::where('idno', $data['idno'])->exists()) {
            return back()
                ->withInput($request->only('idno', 'username'))
                ->with('error', 'This ID number is already registered. Please sign in.');
        }

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

        if (!(new GoogleAuthenticator())->checkCode($secret, $request->code)) {
            return redirect()
                ->route('register.setup')
                ->with('error', 'Invalid Google Authenticator code.');
        }

        // Re-check in case HRIS access changed while setting up
        if (!$this->hasAccountingAccess($pending['idno'])) {
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

    // hris.users.access is a comma-separated list, e.g. "HR,ACCOUNTING,EMPLOYEE PORTAL"
    private function hasAccountingAccess(string $idno): bool
    {
        $access = DB::connection('hris')
            ->table('users')
            ->where('idno', $idno)
            ->value('access');

        if (!$access) {
            return false;
        }

        $accessList = array_map(fn ($item) => strtoupper(trim($item)), explode(',', $access));

        return in_array('ACCOUNTING', $accessList, true);
    }
}
