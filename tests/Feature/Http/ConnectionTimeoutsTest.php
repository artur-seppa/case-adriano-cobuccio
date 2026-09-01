<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Session-level SETs persist on the reused test connection — reset first.
    DB::statement('SET lock_timeout = DEFAULT');
    DB::statement('SET statement_timeout = DEFAULT');

    Route::middleware('web')->get('/__probe/timeouts', fn () => [
        'lock' => DB::selectOne('SHOW lock_timeout')->lock_timeout,
        'stmt' => DB::selectOne('SHOW statement_timeout')->statement_timeout,
    ]);

    Route::get('/__probe/no-timeouts', fn () => [
        'lock' => DB::selectOne('SHOW lock_timeout')->lock_timeout,
    ]);
});

it('applies per-request postgres timeouts on the web guard', function () {
    $this->getJson('/__probe/timeouts')
        ->assertOk()
        ->assertJson(['lock' => '3s', 'stmt' => '5s']);
});

it('does not apply the timeouts to a route outside the web/api guards', function () {
    $this->getJson('/__probe/no-timeouts')
        ->assertOk()
        ->assertJson(['lock' => '0']); // still the server default — middleware never ran
});
