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

it('lets only the initiator reverse, and not a reversal, and not twice', function () {
    [$alice, $bob, $transaction] = transferFixture();

    expect($alice->can('reverse', $transaction))->toBeTrue()
        ->and($bob->can('reverse', $transaction))->toBeFalse();

    $reversal = app(ReverseTransaction::class)(new ReversalData(
        transactionId: $transaction->id, reason: ReversalReason::UserRequest, initiatedByUserId: $alice->id,
    ));

    expect($alice->can('reverse', $transaction->fresh()))->toBeFalse() // already reversed
        ->and($alice->can('reverse', $reversal))->toBeFalse();          // cannot reverse a reversal
});

it('lets the depositor view and reverse their own deposit', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    $deposit = app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));

    expect($user->can('view', $deposit))->toBeTrue()
        ->and($user->can('reverse', $deposit))->toBeTrue();
});
