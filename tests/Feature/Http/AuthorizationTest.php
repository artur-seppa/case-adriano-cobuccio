<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;

function transferFixture(): array
{
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));
    $transaction = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(3000),
    ));

    return [$alice, $bob, $transaction];
}

it('lets the initiator and the counterparty view a transaction, no one else', function () {
    [$alice, $bob, $transaction] = transferFixture();
    $carol = User::factory()->create();
    Wallet::factory()->forUser($carol)->create();

    expect($alice->can('view', $transaction))->toBeTrue()
        ->and($bob->can('view', $transaction))->toBeTrue()
        ->and($carol->can('view', $transaction))->toBeFalse();
});

it('authorizes reverse on ownership only — business rules are the action\'s job', function () {
    [$alice, $bob, $transaction] = transferFixture();

    // ownership: only the initiator is authorized to *ask*
    expect($alice->can('reverse', $transaction))->toBeTrue()
        ->and($bob->can('reverse', $transaction))->toBeFalse();

    $reversal = app(ReverseTransaction::class)(new ReversalData(
        transactionId: $transaction->id, reason: ReversalReason::UserRequest, initiatedByUserId: $alice->id,
    ));

    // already-reversed and reverse-a-reversal still pass the policy (alice owns
    // both) — the endpoint returns 409 / 422 from the action, not 403.
    expect($alice->can('reverse', $transaction->fresh()))->toBeTrue()
        ->and($alice->can('reverse', $reversal))->toBeTrue();
});

it('lets the depositor view and reverse their own deposit', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    $deposit = app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));

    expect($user->can('view', $deposit))->toBeTrue()
        ->and($user->can('reverse', $deposit))->toBeTrue();
});
