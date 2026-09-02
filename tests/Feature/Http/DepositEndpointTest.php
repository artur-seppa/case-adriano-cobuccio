<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function depositUser(int $balanceCents = 0): User
{
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->withBalanceCents($balanceCents)->create();

    return $user;
}

it('creates a deposit and returns 201 with the transaction shape', function () {
    $user = depositUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits',
            ['amount' => '150.00', 'currency' => 'BRL', 'funding_method' => 'pix'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.type', 'deposit')
        ->assertJsonPath('data.amount', '150.00')
        ->assertJsonPath('data.amount_cents', 15000)
        ->assertJsonPath('data.direction', 'in')
        ->assertJsonPath('data.counterparty.label', 'Depósito')
        ->assertHeader('X-Request-Id')
        ->assertHeader('Idempotency-Replayed', 'false');

    expect(Wallet::where('user_id', $user->id)->value('balance_cents'))->toBe(15000);
});

it('adds a deposit to a negative balance', function () {
    $user = depositUser(-5000);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', ['amount' => '20.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    expect(Wallet::where('user_id', $user->id)->value('balance_cents'))->toBe(-3000);
});

it('captures the transaction id on the idempotency row and replays it', function () {
    $user = depositUser();
    $key = (string) Str::uuid();
    $body = ['amount' => '30.00', 'currency' => 'BRL'];

    $first = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', $body, ['Idempotency-Key' => $key])->assertCreated();

    $second = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', $body, ['Idempotency-Key' => $key])
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    expect(DB::table('idempotency_keys')->where('key', $key)->value('transaction_id'))
        ->toBe($first->json('data.id'));
    expect(Wallet::where('user_id', $user->id)->value('balance_cents'))->toBe(3000);
});

it('rejects an invalid amount with 422 problem+json', function () {
    $user = depositUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', ['amount' => '-5.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://wallet.test/problems/validation-failed');
});

it('requires an Idempotency-Key', function () {
    $user = depositUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', ['amount' => '10.00', 'currency' => 'BRL'])
        ->assertStatus(400)
        ->assertJsonPath('type', 'https://wallet.test/problems/idempotency-key-required');
});
