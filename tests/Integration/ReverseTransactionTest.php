<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Exceptions\CannotReverseReversalException;
use App\Domain\Wallet\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    Wallet::factory()->forUser($this->alice)->create();
    Wallet::factory()->forUser($this->bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $this->alice->id, amount: Money::fromCents(10000)));
    $this->transfer = app(TransferFunds::class)(new TransferData(
        senderId: $this->alice->id, recipientId: $this->bob->id, amount: Money::fromCents(4000),
    ));
});

it('reverses a transfer with mirrored entries, leaving the original immutable', function () {
    $reversal = app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id,
        reason: ReversalReason::UserRequest,
        initiatedByUserId: $this->alice->id,
    ));

    expect($reversal->type)->toBe(TransactionType::Reversal)
        ->and($reversal->reversal_of_transaction_id)->toBe($this->transfer->id)
        ->and($this->transfer->fresh()->isReversed())->toBeTrue()
        ->and(Wallet::where('user_id', $this->alice->id)->value('balance_cents'))->toBe(10000)
        ->and(Wallet::where('user_id', $this->bob->id)->value('balance_cents'))->toBe(0);
});

it('can push the counterparty negative', function () {
    // Bob spends what he received, then Alice reverses.
    $carol = User::factory()->create();
    Wallet::factory()->forUser($carol)->create();
    app(TransferFunds::class)(new TransferData(senderId: $this->bob->id, recipientId: $carol->id, amount: Money::fromCents(4000)));

    app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->alice->id,
    ));

    expect(Wallet::where('user_id', $this->bob->id)->value('balance_cents'))->toBe(-4000);
});

it('blocks a second reversal', function () {
    app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->alice->id,
    ));

    expect(fn () => app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->alice->id,
    )))->toThrow(TransactionAlreadyReversedException::class);
});

it('blocks reversing a reversal', function () {
    $reversal = app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->alice->id,
    ));

    expect(fn () => app(ReverseTransaction::class)(new ReversalData(
        transactionId: $reversal->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->alice->id,
    )))->toThrow(CannotReverseReversalException::class);
});

it('rejects a user_request reversal from a non-initiator', function () {
    expect(fn () => app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $this->bob->id,
    )))->toThrow(AuthorizationException::class);
});

it('allows a system reversal for inconsistency without an initiator', function () {
    $reversal = app(ReverseTransaction::class)(new ReversalData(
        transactionId: $this->transfer->id, reason: ReversalReason::Inconsistency, initiatedByUserId: null,
    ));

    expect($reversal->initiator_id)->toBeNull()
        ->and($reversal->reversal_reason)->toBe(ReversalReason::Inconsistency);
});
