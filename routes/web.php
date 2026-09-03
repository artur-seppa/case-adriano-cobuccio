<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// API-only project — no web frontend. `/` is a tiny index pointing at the
// contract and the health check.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'docs' => url('/docs/api'),
    'openapi' => url('/docs/api.json'),
    'health' => url('/up'),
]));

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
});
