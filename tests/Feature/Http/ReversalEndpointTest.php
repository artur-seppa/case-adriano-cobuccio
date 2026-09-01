<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Str;

function seededTransfer(): array
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(20000)));
    $transaction = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(8000),
    ));

    return [$alice, $bob, $transaction];
}

it('lets the initiator reverse their transfer', function () {
    [$alice, $bob, $transaction] = seededTransfer();

    $this->actingAs($alice, 'sanctum')
        ->postJson("/api/v1/transactions/{$transaction->id}/reversal",
            ['note' => 'mudei de ideia'], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()
        ->assertJsonPath('data.type', 'reversal');

    expect(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(0)
        ->and(Wallet::where('user_id', $alice->id)->value('balance_cents'))->toBe(20000);
});

it('forbids a non-initiator from reversing (403)', function () {
    [, $bob, $transaction] = seededTransfer();

    $this->actingAs($bob, 'sanctum')
        ->postJson("/api/v1/transactions/{$transaction->id}/reversal",
            [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(403)
        ->assertJsonPath('type', 'https://wallet.test/problems/forbidden');
});

it('returns 409 when reversing twice', function () {
    [$alice, , $transaction] = seededTransfer();

    $this->actingAs($alice, 'sanctum')
        ->postJson("/api/v1/transactions/{$transaction->id}/reversal",
            [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

    $this->actingAs($alice, 'sanctum')
        ->postJson("/api/v1/transactions/{$transaction->id}/reversal",
            [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(409)
        ->assertJsonPath('type', 'https://wallet.test/problems/transaction-already-reversed');
});

it('returns 404 for an unknown transaction id', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/transactions/01JZZZNONEXISTENT0000000000/reversal',
            [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(404);
});
