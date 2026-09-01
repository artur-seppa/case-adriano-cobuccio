<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\BalanceReconciler;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Tests\Support\ConcurrencyHarness;

beforeEach(fn () => extension_loaded('pcntl') || $this->markTestSkipped('pcntl not available'));

it('does not surface deadlocks when transfers cross in both directions', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(20000)));
    app(DepositFunds::class)(new DepositData(userId: $bob->id, amount: Money::fromCents(20000)));

    $results = ConcurrencyHarness::run(20, function (int $i) use ($alice, $bob) {
        [$from, $to] = $i % 2 === 0 ? [$alice, $bob] : [$bob, $alice];
        app(TransferFunds::class)(new TransferData(
            senderId: $from->id, recipientId: $to->id, amount: Money::fromCents(100),
        ));
    });

    expect(collect($results)->where('ok', true)->count())->toBe(20);

    $sum = (int) Wallet::whereIn('user_id', [$alice->id, $bob->id])->sum('balance_cents');
    expect($sum)->toBe(40000)
        ->and(app(BalanceReconciler::class)->isHealthy(app(BalanceReconciler::class)->check()))->toBeTrue();
});
