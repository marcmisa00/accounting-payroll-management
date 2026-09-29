<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccountingAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('accounting_authenticated')) {
            return redirect()->route('sso.login');
        }

        return $next($request);
    }
}