<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * EnsureIdempotency is exercised here against a throwaway route so the middleware
 * gets full coverage before the real money endpoints land in Task 11. Task 11's
 * DepositEndpointTest then covers it end-to-end on the actual routes.
 */
beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/__probe/idem', function () {
            // fresh token per real execution — a replay must return the FIRST one.
            // No `data.id` here: this probe has no backing transaction row, and the
            // middleware's transaction_id capture is covered end-to-end in Task 11.
            return response()->json(['data' => ['token' => (string) Str::random(24)]], 201);
        });
});

function idemUser(): User
{
    return User::factory()->create();
}

it('rejects a call without an Idempotency-Key', function () {
    $this->actingAs(idemUser(), 'sanctum')
        ->postJson('/__probe/idem', ['amount' => '10.00'])
        ->assertStatus(400)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/idempotency-key-required');
});

it('rejects a non-UUID Idempotency-Key', function () {
    $this->actingAs(idemUser(), 'sanctum')
        ->postJson('/__probe/idem', ['amount' => '10.00'], ['Idempotency-Key' => 'not-a-uuid'])
        ->assertStatus(400);
});

it('runs the request once and replays the stored response on repeat', function () {
    $user = idemUser();
    $key = (string) Str::uuid();
    $payload = ['amount' => '25.00', 'currency' => 'BRL'];

    $first = $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/idem', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'false');

    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/idem', $payload, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($second->json('data.token'))->toBe($first->json('data.token'));
    expect(DB::table('idempotency_keys')->where('key', $key)->value('status'))->toBe('completed');
});

it('returns 422 when the same key is reused with a different body', function () {
    $user = idemUser();
    $key = (string) Str::uuid();

    $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/idem', ['amount' => '25.00'], ['Idempotency-Key' => $key])
        ->assertCreated();

    $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/idem', ['amount' => '99.00'], ['Idempotency-Key' => $key])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://wallet.test/problems/idempotency-key-reused');
});

it('never replays another user\'s response for a colliding key', function () {
    $alice = idemUser();
    $bob = idemUser();
    $key = (string) Str::uuid();
    $body = ['amount' => '25.00', 'currency' => 'BRL'];

    $aliceRes = $this->actingAs($alice, 'sanctum')
        ->postJson('/__probe/idem', $body, ['Idempotency-Key' => $key])->assertCreated();

    // Bob presents Alice's key with the same body — must NOT get Alice's response.
    $this->actingAs($bob, 'sanctum')
        ->postJson('/__probe/idem', $body, ['Idempotency-Key' => $key])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://wallet.test/problems/idempotency-key-reused');

    expect(DB::table('idempotency_keys')->where('key', $key)->value('user_id'))->toBe($alice->id);
});

it('cleans up the locked row when the handler 5xxs, so a retry can proceed', function () {
    $user = idemUser();
    $key = (string) Str::uuid();

    Route::middleware(['api', 'auth:sanctum', 'idempotency'])
        ->post('/__probe/boom', fn () => abort(500));

    $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/boom', ['x' => 1], ['Idempotency-Key' => $key])
        ->assertStatus(500);

    expect(DB::table('idempotency_keys')->where('key', $key)->exists())->toBeFalse();
});

it('returns 409 while a key is still locked', function () {
    $user = idemUser();
    $key = (string) Str::uuid();

    DB::table('idempotency_keys')->insert([
        'key' => $key, 'user_id' => $user->id, 'method' => 'POST', 'path' => '__probe/idem',
        'request_fingerprint' => str_repeat('0', 64), 'status' => 'locked',
        'locked_at' => now(), 'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/__probe/idem', ['amount' => '25.00'], ['Idempotency-Key' => $key])
        ->assertStatus(409)
        ->assertHeader('Retry-After')
        ->assertJsonPath('type', 'https://wallet.test/problems/idempotency-conflict');
});

it('prunes expired keys via the console command', function () {
    DB::table('idempotency_keys')->insert([
        'key' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id,
        'method' => 'POST', 'path' => 'x', 'request_fingerprint' => str_repeat('0', 64),
        'status' => 'completed', 'locked_at' => now()->subDays(2), 'expires_at' => now()->subDay(),
    ]);
    DB::table('idempotency_keys')->insert([
        'key' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id,
        'method' => 'POST', 'path' => 'x', 'request_fingerprint' => str_repeat('0', 64),
        'status' => 'locked', 'locked_at' => now(), 'expires_at' => now()->addDay(),
    ]);

    $this->artisan('idempotency:prune')->assertExitCode(0);

    expect(DB::table('idempotency_keys')->count())->toBe(1);
});
