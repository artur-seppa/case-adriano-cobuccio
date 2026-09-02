<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Str;

function transferPair(int $senderCents = 0): array
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    if ($senderCents > 0) {
        app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents($senderCents)));
    }

    return [$alice, $bob];
}

it('transfers between two users and returns 201', function () {
    [$alice, $bob] = transferPair(30000);

    $this->actingAs($alice, 'sanctum')
        ->postJson('/api/v1/transfers',
            ['recipient' => $bob->email, 'amount' => '120.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.type', 'transfer')
        ->assertJsonPath('data.direction', 'out')
        ->assertJsonPath('data.counterparty.name', $bob->name);

    expect(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(12000)
        ->and(Wallet::where('user_id', $alice->id)->value('balance_cents'))->toBe(18000);
});

it('accepts a recipient given by ULID', function () {
    [$alice, $bob] = transferPair(5000);

    $this->actingAs($alice, 'sanctum')
        ->postJson('/api/v1/transfers',
            ['recipient' => $bob->id, 'amount' => '10.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated();

    expect(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(1000);
});

it('rejects an overdraw with 422 problem+json carrying available/requested', function () {
    [$alice, $bob] = transferPair(0);

    $this->actingAs($alice, 'sanctum')
        ->postJson('/api/v1/transfers',
            ['recipient' => $bob->email, 'amount' => '10.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('type', 'https://wallet.test/problems/insufficient-funds')
        ->assertJsonStructure(['available', 'requested']);

    expect(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(0);
});

it('rejects a self-transfer and an unknown recipient with 422', function () {
    [$alice] = transferPair(1000);

    $this->actingAs($alice, 'sanctum')
        ->postJson('/api/v1/transfers',
            ['recipient' => $alice->email, 'amount' => '1.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('errors.recipient.0', fn ($m) => str_contains($m, 'yourself'));

    $this->actingAs($alice, 'sanctum')
        ->postJson('/api/v1/transfers',
            ['recipient' => 'ghost@example.test', 'amount' => '1.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertJsonPath('errors.recipient.0', fn ($m) => str_contains($m, 'No user'));
});
