<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    private const PATTERN = '/^[A-Za-z0-9._-]{8,128}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $id = preg_match(self::PATTERN, $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $id);
        app()->instance('request_id', $id);

        Context::add('request_id', $id);
        Context::add('ip', $request->ip());
        Context::add('user_id', optional($request->user())->id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
