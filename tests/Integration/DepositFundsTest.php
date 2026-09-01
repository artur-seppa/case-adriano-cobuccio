<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Exceptions\CurrencyMismatchException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\SystemWallets;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->forUser($this->user)->create();
});

it('credits the user wallet and debits external_world', function () {
    Event::fake([FundsDeposited::class]);

    $tx = app(DepositFunds::class)(new DepositData(
        userId: $this->user->id, amount: Money::fromCents(15000),
    ));

    expect($tx->type)->toBe(TransactionType::Deposit)
        ->and($this->wallet->fresh()->balance_cents)->toBe(15000)
        ->and(SystemWallets::externalWorld()->fresh()->balance_cents)->toBe(-15000);

    Event::assertDispatched(FundsDeposited::class, fn ($e) => $e->transaction->is($tx));
});

it('adds to a negative balance', function () {
    $this->wallet->update(['balance_cents' => -5000]);

    app(DepositFunds::class)(new DepositData(userId: $this->user->id, amount: Money::fromCents(2000)));

    expect($this->wallet->fresh()->balance_cents)->toBe(-3000);
});

it('short-circuits on a repeated idempotency key', function () {
    $key = (string) Str::uuid();
    $a = app(DepositFunds::class)(new DepositData(userId: $this->user->id, amount: Money::fromCents(100), idempotencyKey: $key));
    $b = app(DepositFunds::class)(new DepositData(userId: $this->user->id, amount: Money::fromCents(100), idempotencyKey: $key));

    expect($b->id)->toBe($a->id)
        ->and($this->wallet->fresh()->balance_cents)->toBe(100);
});

it('rejects a currency mismatch', function () {
    expect(fn () => app(DepositFunds::class)(new DepositData(
        userId: $this->user->id, amount: Money::fromCents(100, 'USD'),
    )))->toThrow(CurrencyMismatchException::class);
});
