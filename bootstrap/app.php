<?php

use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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

        $middleware->api(prepend: [
            // TODO Task 2: uncomment
            // \App\Http\Middleware\AssignRequestId::class,
        ]);
        $middleware->web(append: [
            // TODO Task 2: uncomment
            // \App\Http\Middleware\AssignRequestId::class,
            // \App\Http\Middleware\SetConnectionTimeouts::class,
        ]);

        $middleware->alias([
            'idempotency' => EnsureIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
