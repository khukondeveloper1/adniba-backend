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
        $this->app->register(RepositoryServiceProvider::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // SDK — per app_id
        RateLimiter::for('sdk', function (Request $request) {
            $appId   = optional($request->attributes->get('sdk_app'))->id ?? $request->ip();
            $isEvent = $request->is('api/v1/ads/event');
            return Limit::perMinute($isEvent ? 1000 : 300)
                ->by('sdk:' . $appId)
                ->response(fn () => response()->json(['status' => 'error', 'message' => 'Rate limit exceeded.'], 429));
        });

        // Admin — per IP
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(60)
                ->by('admin:' . $request->ip())
                ->response(fn () => response()->json(['status' => 'error', 'message' => 'Too many admin requests.'], 429));
        });

        // Developer — per user_id
        RateLimiter::for('developer', function (Request $request) {
            $userId = optional($request->attributes->get('developer'))->id ?? $request->ip();
            return Limit::perMinute(120)
                ->by('dev:' . $userId)
                ->response(fn () => response()->json(['status' => 'error', 'message' => 'Too many requests.'], 429));
        });

        // External API — per api_key (app)
        RateLimiter::for('external', function (Request $request) {
            $app = $request->attributes->get('external_app');
            $key = $app ? 'ext:' . $app->id : 'ext:' . $request->ip();
            return Limit::perMinute(60)
                ->by($key)
                ->response(fn () => response()->json(['status' => 'error', 'message' => 'Rate limit exceeded.'], 429));
        });
    }
}
