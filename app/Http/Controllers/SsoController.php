<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SsoController extends Controller
{
    public function test()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Laravel SSO endpoint is working.',
        ]);
    }

    public function login(Request $request)
    {
        $idno = $request->query('idno');
        $timestamp = $request->query('timestamp');
        $signature = $request->query('signature');

        // 1. Required parameters
        if (!$idno || !$timestamp || !$signature) {
            abort(403, 'Invalid SSO request.');
        }

        // 2. Request must be recent
        if (abs(time() - (int) $timestamp) > 60) {
            abort(403, 'SSO request has expired.');
        }

        // 3. Generate the signature Laravel expects
        $expectedSignature = hash_hmac(
            'sha256',
            $idno . '|' . $timestamp,
            config('app.portal_sso_secret')
        );

        // 4. Compare signatures securely
        if (!hash_equals($expectedSignature, $signature)) {
            abort(403, 'Invalid SSO signature.');
        }

        // 5. Store Portal identity in Laravel session
        session([
            'portal_authenticated' => true,
            'portal_idno' => $idno,
        ]);

        // 6. Go to Laravel 2FA verification
        return redirect()->route('accounting.verify');
    }
}