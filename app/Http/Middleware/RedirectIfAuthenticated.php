<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated as Base;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headless-API counterpart to `bootstrap/app.php`'s
 * `redirectGuestsTo(fn () => null)`: that neutralises the `auth` middleware's
 * redirect-to-login for JSON requests; this does the same for the `guest`
 * middleware's redirect-to-home.
 *
 * Stock {@see Base} unconditionally 302s an already-authenticated caller to the
 * app home — built as an absolute URL on the API's own origin. Behind the SPA's
 * same-origin proxy the browser's `fetch` follows that 302 cross-origin to the
 * backend root, which is not in `config/cors.php` `paths`, so it dies with
 * "CORS header 'Access-Control-Allow-Origin' missing" and the login/register
 * call rejects as a network error.
 *
 * For a request that expects JSON, answer `204`: the caller asked to be
 * authenticated and already is. Plain browser navigations still redirect.
 */
class RedirectIfAuthenticated extends Base
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if (! $request->expectsJson()) {
            return parent::handle($request, $next, ...$guards);
        }

        foreach (empty($guards) ? [null] : $guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return response()->noContent();
            }
        }

        return $next($request);
    }
}
