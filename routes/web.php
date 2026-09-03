<?php

use App\Http\Controllers\MetricsController;
use App\Http\Middleware\SetConnectionTimeouts;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// API-only project — no web frontend. `/` is a tiny index pointing at the
// contract and the health check.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'docs' => url('/docs/api'),
    'openapi' => url('/docs/api.json'),
    'health' => url('/up'),
]));

// Neither route needs the session/cookie machinery the `web` group carries
// (SESSION_DRIVER=database), nor `SetConnectionTimeouts`'s own `DB::statement`
// calls: with either in the pipeline, a Postgres outage throws *before*
// either closure runs, turning the `/health` DB-down case into a generic 500
// instead of the documented `503 {"db":"down"}` — and every 10s Docker
// healthcheck / metrics scrape would otherwise write a spurious `sessions`
// row and issue 2 extra statements for nothing.
$withoutSession = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
    SetConnectionTimeouts::class,
];

Route::get('/health', function () {
    $checks = ['db' => 'ok', 'redis' => 'ok'];

    try {
        DB::connection()->getPdo();
        DB::select('select 1');
    } catch (Throwable $e) {
        $checks['db'] = 'down';
    }

    try {
        Redis::connection()->ping();
    } catch (Throwable $e) {
        $checks['redis'] = 'down';
    }

    $ok = ! in_array('down', $checks, true);

    return response()->json(['status' => $ok ? 'ok' : 'degraded', ...$checks], $ok ? 200 : 503);
})->withoutMiddleware($withoutSession);

Route::get('/metrics', MetricsController::class)->withoutMiddleware($withoutSession);
