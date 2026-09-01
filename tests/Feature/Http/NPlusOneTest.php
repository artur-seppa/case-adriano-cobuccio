<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists transactions without an N+1 explosion', function () {
    $alice = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(1_000_000)));

    foreach (range(1, 15) as $i) {
        $bob = User::factory()->create();
        Wallet::factory()->forUser($bob)->create();
        app(TransferFunds::class)(new TransferData(
            senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(100),
        ));
    }

    DB::enableQueryLog();
    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/transactions?per_page=20')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // pagination + a bounded set of eager-load queries — not ~1 per row.
    expect($queryCount)->toBeLessThan(15);
});

it('renders the statement without an N+1 explosion', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    Wallet::factory()->forUser($other)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(500_000)));
    foreach (range(1, 12) as $i) {
        app(TransferFunds::class)(new TransferData(
            senderId: $user->id, recipientId: $other->id, amount: Money::fromCents(100),
        ));
    }

    DB::enableQueryLog();
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet/statement?per_page=20')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(10);
});
