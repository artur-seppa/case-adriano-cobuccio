<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Tests\Support\ConcurrencyHarness;

beforeEach(fn () => extension_loaded('pcntl') || $this->markTestSkipped('pcntl not available'));

it('produces exactly one reversal row under concurrent reversal attempts', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));
    $transfer = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));

    ConcurrencyHarness::run(5, function () use ($transfer, $alice) {
        app(ReverseTransaction::class)(new ReversalData(
            transactionId: $transfer->id, reason: ReversalReason::UserRequest, initiatedByUserId: $alice->id,
        ));
    });

    expect(Transaction::where('type', TransactionType::Reversal)
        ->where('reversal_of_transaction_id', $transfer->id)->count())->toBe(1)
        ->and(Wallet::where('user_id', $alice->id)->value('balance_cents'))->toBe(10000)
        ->and(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(0);
});
