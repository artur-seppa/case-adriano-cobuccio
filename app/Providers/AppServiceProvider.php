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
        // Reads: 60/min per user (spec §10.6). Falls back to IP for the rare
        // unauthenticated hit before auth:sanctum rejects it.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Money writes: 10/min per user (spec §10.6).
        RateLimiter::for('transfers', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()->id));
    }
}
