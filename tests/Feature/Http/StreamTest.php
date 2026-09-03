<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Http\Controllers\Api\V1\StreamController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * Records every Redis::publish call into $sink and returns it. Must be installed
 * BEFORE any Action runs, since the domain events fire synchronously post-commit.
 *
 * @param  array<string, mixed>  $sink
 */
function captureRedisPublishes(array &$sink): void
{
    Redis::shouldReceive('publish')->andReturnUsing(function ($channel, $payload) use (&$sink) {
        $sink[$channel] = json_decode($payload, true);

        return 1;
    });
}

it('requires authentication on the stream route', function () {
    $this->getJson('/api/v1/stream')
        ->assertStatus(401)
        ->assertJsonPath('type', 'https://wallet.test/problems/unauthenticated');
});

it('lifts the request execution-time limit so a long-lived SSE stream is not capped', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    $original = (int) ini_get('max_execution_time');

    try {
        // Stand in for FrankenPHP's REQUEST_MAX_EXECUTION_TIME (30s by default),
        // which would otherwise kill the stream mid-connection.
        set_time_limit(7);

        $request = Request::create('/api/v1/stream', 'GET');
        $request->setUserResolver(fn () => $user);

        (new StreamController)($request);

        expect((int) ini_get('max_execution_time'))->toBe(0);
    } finally {
        set_time_limit($original);
    }
});

it('publishes a compact nudge to both parties on a transfer', function () {
    $published = [];
    captureRedisPublishes($published);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));

    $published = []; // ignore the setup deposit's nudge

    app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));

    expect($published)->toHaveKeys(["user-events:{$alice->id}", "user-events:{$bob->id}"]);
    expect($published["user-events:{$bob->id}"])
        ->toMatchArray(['type' => 'transaction.received', 'direction' => 'in']);
    expect($published["user-events:{$bob->id}"]['amount_formatted'])->toContain('40,00');
    expect($published["user-events:{$alice->id}"]['type'])->toBe('transaction.sent');
});

it('publishes deposit.completed on a deposit', function () {
    $published = [];
    captureRedisPublishes($published);

    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(2500)));

    expect($published)->toHaveKey("user-events:{$user->id}");
    expect($published["user-events:{$user->id}"])
        ->toMatchArray(['type' => 'deposit.completed', 'direction' => 'in']);
});

it('publishes transaction.reversed to both parties on a reversal', function () {
    $published = [];
    captureRedisPublishes($published);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));
    $transfer = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));

    $published = [];

    app(ReverseTransaction::class)(new ReversalData(
        transactionId: $transfer->id,
        reason: ReversalReason::UserRequest,
        initiatedByUserId: $alice->id,
    ));

    expect(array_keys($published))->toContain("user-events:{$alice->id}", "user-events:{$bob->id}");
    expect($published["user-events:{$bob->id}"]['type'])->toBe('transaction.reversed');
});
