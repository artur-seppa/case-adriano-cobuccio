<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\BalanceReconciler;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Tests\Support\ConcurrencyHarness;

beforeEach(fn () => extension_loaded('pcntl') || $this->markTestSkipped('pcntl not available'));

it('never double-spends under concurrent transfers', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));

    $results = ConcurrencyHarness::run(10, function () use ($alice, $bob) {
        app(TransferFunds::class)(new TransferData(
            senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(2000),
        ));
    });

    $ok = collect($results)->where('ok', true)->count();
    $failed = collect($results)->where('ok', false)->count();

    expect($ok)->toBe(5)
        ->and($failed)->toBe(5)
        ->and(collect($results)->where('ok', false)->every(
            fn ($r) => str_contains($r['error'], class_basename(InsufficientFundsException::class))
        ))->toBeTrue();

    expect(Wallet::where('user_id', $alice->id)->value('balance_cents'))->toBe(0)
        ->and(Wallet::where('user_id', $bob->id)->value('balance_cents'))->toBe(10000);

    $reconcile = app(BalanceReconciler::class)->check();
    expect(app(BalanceReconciler::class)->isHealthy($reconcile))->toBeTrue();
});
