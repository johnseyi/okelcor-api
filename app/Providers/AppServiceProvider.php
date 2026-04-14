<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Quote requests: 5 per IP per hour (spec §4.6)
        RateLimiter::for('quote-form', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // Contact / Orders / Newsletter: 10 per IP per hour (spec §4.7, §4.8)
        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        // Search: 30 per IP per minute (spec §4.12)
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // VAT validation: 10 per IP per minute
        RateLimiter::for('vat', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Payments: 20 per IP per minute
        RateLimiter::for('payments', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });
    }
}
