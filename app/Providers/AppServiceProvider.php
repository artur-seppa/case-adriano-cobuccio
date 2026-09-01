<?php

namespace App\Providers;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Listeners\PublishUserEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        // SSE stream opens: 12/min per user (spec §11).
        RateLimiter::for('stream', fn (Request $request) => Limit::perMinute(12)
            ->by($request->user()->id));

        // Post-commit realtime fan-out (spec §6.6 / §11).
        Event::listen(FundsDeposited::class, [PublishUserEvent::class, 'handleDeposited']);
        Event::listen(FundsTransferred::class, [PublishUserEvent::class, 'handleTransferred']);
        Event::listen(TransactionReversed::class, [PublishUserEvent::class, 'handleReversed']);
    }
}
