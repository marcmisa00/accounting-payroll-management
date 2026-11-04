<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

/**
 * Google Authenticator codes. A code is valid for about 90 seconds (one
 * 30-second step either side), so an accepted code is remembered for two
 * minutes and refused if it is entered again.
 */
class AuthenticatorCode
{
    private const USED_SECONDS = 120;

    /**
     * True when $code is valid for $secret and has not been accepted before
     * for $scope (e.g. "user:12" or "hris:2020-001").
     */
    public static function verify(string $secret, string $code, string $scope): bool
    {
        if ($secret === '' || !(new GoogleAuthenticator())->checkCode($secret, $code)) {
            return false;
        }

        // add() only succeeds when the key is not there yet
        return Cache::add('authenticator-used:' . sha1($scope . '|' . $code), true, self::USED_SECONDS);
    }
}
