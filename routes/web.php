<?php

use Illuminate\Support\Facades\Route;

// API-only project — no web frontend. `/` is a tiny index pointing at the
// contract and the health check.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'docs' => url('/docs/api'),
    'openapi' => url('/docs/api.json'),
    'health' => url('/up'),
]));
