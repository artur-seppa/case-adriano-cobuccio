<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Test DB connections are reused across tests in the same process and these
    // SETs are session-level, so reset them before each test to isolate the
    // "console connection untouched" assertion below.
    DB::statement('SET lock_timeout = DEFAULT');
    DB::statement('SET statement_timeout = DEFAULT');

    Route::middleware('web')->get('/__probe/timeouts', function () {
        return [
            'lock' => DB::selectOne('SHOW lock_timeout')->lock_timeout,
            'stmt' => DB::selectOne('SHOW statement_timeout')->statement_timeout,
        ];
    });
});

it('applies per-request postgres timeouts on the web guard', function () {
    $this->getJson('/__probe/timeouts')
        ->assertOk()
        ->assertJson(['lock' => '3s', 'stmt' => '5s']);
});

it('leaves the console connection untouched', function () {
    // fora de um request web, nada de timeout apertado
    expect(DB::selectOne('SHOW lock_timeout')->lock_timeout)->toBe('0');
});
