<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;

function queryFixture(): array
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(50000)));
    $out = app(TransferFunds::class)(new TransferData(senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(3000)));
    $in = app(TransferFunds::class)(new TransferData(senderId: $bob->id, recipientId: $alice->id, amount: Money::fromCents(1000)));

    return [$alice, $bob, $out, $in];
}

it('lists transactions where the user is initiator or counterparty, cursor-paginated', function () {
    [$alice] = queryFixture();

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'type', 'status', 'direction', 'counterparty']], 'links', 'meta'])
        ->assertJsonCount(3, 'data'); // 1 deposit + 1 out + 1 in
});

it('filters by type and by direction', function () {
    [$alice] = queryFixture();

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?type=deposit')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'deposit');

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?direction=out')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.direction', 'out');
});

it('shows a transaction to a participant and 403s a stranger', function () {
    [$alice, $bob, $out] = queryFixture();
    $mallory = User::factory()->create();
    Wallet::factory()->forUser($mallory)->create();

    $this->actingAs($bob, 'sanctum')->getJson("/api/v1/transactions/{$out->id}")
        ->assertOk()->assertJsonPath('data.direction', 'in');

    $this->actingAs($mallory, 'sanctum')->getJson("/api/v1/transactions/{$out->id}")
        ->assertStatus(403)->assertJsonPath('type', 'https://wallet.test/problems/forbidden');
});

it('404s an unknown transaction', function () {
    [$alice] = queryFixture();

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions/01JZZZNONEXISTENT0000000000')
        ->assertStatus(404)->assertJsonPath('type', 'https://wallet.test/problems/not-found');
});

it('rejects a bad per_page or date filter with 422, not 500', function () {
    [$alice] = queryFixture();

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?per_page=-1')
        ->assertStatus(422)->assertJsonPath('type', 'https://wallet.test/problems/validation-failed');

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?per_page=100000')
        ->assertStatus(422);

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?from=not-a-date')
        ->assertStatus(422);
});
