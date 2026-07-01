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
        // High-volume telemetry ingestion — throttle per ingest key / IP.
        RateLimiter::for('prebid-ingest', function (Request $request) {
            $key = $request->header('X-Ingest-Key') ?: $request->ip();

            return Limit::perMinute(600)->by((string) $key);
        });

        // Ad-layout capture is expensive (headless render) — throttle per user.
        RateLimiter::for('page-preview', function (Request $request) {
            return Limit::perMinute(10)->by((string) optional($request->user())->id ?: $request->ip());
        });
    }
}
