<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects a wallet that is neither a valid user nor system wallet', function () {
    expect(fn () => DB::table('wallets')->insert([
        'id' => (string) Str::ulid(),
        'type' => 'user',
        'user_id' => null,        // user type requires user_id
        'reference' => null,
        'currency' => 'BRL',
        'balance_cents' => 0,
        'entry_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows negative wallet balance', function () {
    $userId = (string) Str::ulid();
    DB::table('users')->insert([
        'id' => $userId, 'name' => 'N', 'email' => 'n@example.test',
        'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('wallets')->insert([
        'id' => (string) Str::ulid(), 'type' => 'user', 'user_id' => $userId,
        'reference' => null, 'currency' => 'BRL', 'balance_cents' => -500, 'entry_count' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('wallets')->where('user_id', $userId)->value('balance_cents'))->toBe(-500);
});
