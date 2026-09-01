<?php

namespace App\Providers;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Listeners\PublishUserEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // All Fortify auth routes (login, register, forgot/reset password, ...):
        // 5/min keyed on email+IP, IP-only when there is no email field. Blocks
        // credential stuffing, account-creation floods and forgot-password abuse.
        RateLimiter::for('auth', function (Request $request) {
            $email = (string) $request->input('email', '');
            $key = $email !== ''
                ? mb_strtolower($email).'|'.$request->ip()
                : (string) $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Post-commit realtime fan-out (spec §6.6 / §11).
        Event::listen(FundsDeposited::class, [PublishUserEvent::class, 'handleDeposited']);
        Event::listen(FundsTransferred::class, [PublishUserEvent::class, 'handleTransferred']);
        Event::listen(TransactionReversed::class, [PublishUserEvent::class, 'handleReversed']);

        // OpenAPI docs (/docs/api): open everywhere except production, where an
        // authenticated user is required.
        Gate::define('viewApiDocs', fn ($user = null) => ! app()->environment('production') || $user !== null);

        // Financial app: passwords are at least 10 chars, mixed case + a digit.
        Password::defaults(fn () => app()->isProduction()
            ? Password::min(10)->mixedCase()->numbers()
            : Password::min(10));
    }
}
