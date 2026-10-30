<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Login, register and OTP forms: 5 attempts per minute, counted per form and IP
        RateLimiter::for('accounting-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->path() . '|' . $request->ip());
        });
    }
}
