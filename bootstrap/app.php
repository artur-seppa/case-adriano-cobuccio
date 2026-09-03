<?php

use App\Domain\Wallet\Support\ProblemMapper;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\LogRequest;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetConnectionTimeouts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi(); // Sanctum: EnsureFrontendRequestsAreStateful on the 'api' group

        // Atrás do nginx (rede Docker): confiar no X-Forwarded-* para que
        // scheme/host/URLs assinadas e cookies fiquem coerentes.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.1',
        ]);

        // Headless API: there is no `login` route, so a guest hitting a
        // protected route must never trigger `route('login')` in the auth
        // middleware (it throws RouteNotFoundException → 500). Returning null
        // lets the exception handler answer with a JSON 401.
        $middleware->redirectGuestsTo(fn () => null);

        // Global: every HTTP response (health check + error responses included)
        // must carry X-Request-Id. The `/up` health route has no middleware
        // group in Laravel 12, so group-scoped wiring would miss it.
        $middleware->prepend(AssignRequestId::class);

        // Per-request Postgres timeouts on the request-serving guards only —
        // never console/queue. The `/api/v1` money routes run through `api`.
        $middleware->web(append: [
            SetConnectionTimeouts::class,
        ]);
        $middleware->api(append: [
            SetConnectionTimeouts::class,
        ]);

        // Global, same reason as AssignRequestId above: `/up` has no
        // middleware group, and "1 structured line per request" must cover
        // it too.
        $middleware->append(LogRequest::class);

        $middleware->alias([
            'idempotency' => EnsureIdempotency::class,

            // Symmetric fix for `redirectGuestsTo` above: stock
            // RedirectIfAuthenticated 302s an already-authenticated JSON caller
            // to an absolute app-home URL that the SPA's fetch then follows
            // cross-origin and loses on CORS. Our override answers 204 for JSON.
            'guest' => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a JSON-only API: every path is under /api. Treat all errors as
        // JSON so the handler never tries to redirect to a (non-existent) login
        // view for a plain browser GET.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null; // non-API, non-JSON — let the default handler run
            }

            return ProblemMapper::map($e);
        });
    })->create();
