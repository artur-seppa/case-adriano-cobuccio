<?php

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\BalanceReconciler;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('builds a consistent walkthrough dataset', function () {
    $this->seed(DemoSeeder::class);

    expect(User::count())->toBe(3)
        ->and(Wallet::whereNotNull('user_id')->count())->toBe(3)
        ->and(Transaction::where('type', TransactionType::Deposit)->count())->toBe(3)
        ->and(Transaction::where('type', TransactionType::Transfer)->count())->toBe(3)
        ->and(Transaction::where('type', TransactionType::Reversal)->count())->toBe(1);

    // every user wallet is funded and verified for the money endpoints
    User::all()->each(function (User $user) {
        expect($user->email_verified_at)->not->toBeNull()
            ->and($user->wallet->balance->isPositive())->toBeTrue();
    });

    // the ledger the seeder wrote through the real Actions reconciles
    $reconciler = app(BalanceReconciler::class);
    expect($reconciler->isHealthy($reconciler->check()))->toBeTrue();
});

it('is idempotent — a second run is a no-op, not a duplicate-email crash', function () {
    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    expect(User::count())->toBe(3);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        (new DemoSeeder)->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(User::count())->toBe(0);
});
