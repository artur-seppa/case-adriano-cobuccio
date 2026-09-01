<?php

use Illuminate\Support\Facades\Route;

// Placeholder so the stateful `auth:sanctum` guard can be exercised before Task 11
// replaces this file with the real domain routes.
Route::middleware('auth:sanctum')->get('/wallet', fn () => abort(501));
