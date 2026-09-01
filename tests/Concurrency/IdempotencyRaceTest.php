<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\ConcurrencyHarness;

beforeEach(fn () => extension_loaded('pcntl') || $this->markTestSkipped('pcntl not available'));

it('creates exactly one transaction for a shared idempotency key', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    $key = (string) Str::uuid();

    ConcurrencyHarness::run(5, function () use ($user, $key) {
        app(DepositFunds::class)(new DepositData(
            userId: $user->id, amount: Money::fromCents(3000), idempotencyKey: $key,
        ));
    });

    expect(Transaction::where('idempotency_key', $key)->count())->toBe(1)
        ->and(Wallet::where('user_id', $user->id)->value('balance_cents'))->toBe(3000);
});
