<?php

namespace App\Http\Middleware;

use App\Support\Metrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationSeconds = microtime(true) - $start;

        Log::info('request.completed', [
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round($durationSeconds * 1000),
            'user_id' => optional($request->user())->id,
            'request_id' => $request->attributes->get('request_id'),
            'ip' => $request->ip(),
        ]);

        // The route *pattern* (`api/v1/transactions/{transaction}/reversal`),
        // never the resolved path — unbounded, request-controlled label
        // values (ULIDs, or any garbage path a client sends) would otherwise
        // create a permanent, ever-growing set of histogram series in Redis.
        Metrics::histogram(
            'http_server_request_duration_seconds', 'HTTP server request duration, in seconds.',
            $durationSeconds,
            ['method', 'route', 'status'],
            [$request->getMethod(), $request->route()?->uri() ?? 'unmatched', (string) $response->getStatusCode()],
            [0.05, 0.1, 0.25, 0.5, 1, 2.5, 5],
        );

        return $response;
    }
}
