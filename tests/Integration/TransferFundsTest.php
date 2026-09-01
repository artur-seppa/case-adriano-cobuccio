<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    Wallet::factory()->forUser($this->alice)->create();
    Wallet::factory()->forUser($this->bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $this->alice->id, amount: Money::fromCents(10000)));
});

it('moves balance from sender to recipient', function () {
    Event::fake([FundsTransferred::class]);

    $tx = app(TransferFunds::class)(new TransferData(
        senderId: $this->alice->id, recipientId: $this->bob->id, amount: Money::fromCents(3000),
    ));

    expect($tx->type)->toBe(TransactionType::Transfer)
        ->and(Wallet::where('user_id', $this->alice->id)->value('balance_cents'))->toBe(7000)
        ->and(Wallet::where('user_id', $this->bob->id)->value('balance_cents'))->toBe(3000);

    Event::assertDispatched(FundsTransferred::class);
});

it('rejects an overdraw and writes nothing', function () {
    $before = Transaction::count();

    expect(fn () => app(TransferFunds::class)(new TransferData(
        senderId: $this->alice->id, recipientId: $this->bob->id, amount: Money::fromCents(999999),
    )))->toThrow(InsufficientFundsException::class);

    expect(Transaction::count())->toBe($before)
        ->and(Wallet::where('user_id', $this->alice->id)->value('balance_cents'))->toBe(10000)
        ->and(Wallet::where('user_id', $this->bob->id)->value('balance_cents'))->toBe(0);
});

it('rejects a self-transfer', function () {
    expect(fn () => app(TransferFunds::class)(new TransferData(
        senderId: $this->alice->id, recipientId: $this->alice->id, amount: Money::fromCents(100),
    )))->toThrow(InvalidArgumentException::class);
});
