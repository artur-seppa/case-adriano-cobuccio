<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\BalanceReconciler;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;

it('reports no drift and zero global balance on a clean ledger', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));

    $result = app(BalanceReconciler::class)->check();

    expect($result['drifts'])->toBe([])
        ->and($result['global_balance_cents'])->toBe(0)
        ->and(app(BalanceReconciler::class)->isHealthy($result))->toBeTrue();
});

it('detects an injected drift', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));

    $wallet->newQuery()->whereKey($wallet->id)->update(['balance_cents' => 4200]); // corrupt cache

    $result = app(BalanceReconciler::class)->check();

    expect($result['drifts'])->toHaveCount(1)
        ->and($result['drifts'][0]['wallet_id'])->toBe($wallet->id)
        ->and($result['drifts'][0]['computed'])->toBe(5000)
        ->and($result['drifts'][0]['cached'])->toBe(4200);
});

it('exits non-zero via the command when drift exists', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));
    Wallet::whereKey($wallet->id)->update(['balance_cents' => 1]);

    $this->artisan('wallet:reconcile')->assertExitCode(1);
    $this->artisan('wallet:reconcile --fix')->assertExitCode(0);

    expect($wallet->fresh()->balance_cents)->toBe(5000);
});
