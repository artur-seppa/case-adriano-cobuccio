<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetConnectionTimeouts
{
    public function handle(Request $request, Closure $next): Response
    {
        DB::statement("SET lock_timeout = '3s'");
        DB::statement("SET statement_timeout = '5s'");

        return $next($request);
    }
}
