<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Models\User;
use Illuminate\Http\Request;

function requestAs(User $user): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('renders the wallet resource shape', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(15000)));

    $data = (new WalletResource($user->wallet()->first()))->toArray(requestAs($user));

    expect($data)->toMatchArray([
        'currency' => 'BRL',
        'balance' => '150.00',
        'balance_cents' => 15000,
    ]);
    expect($data['balance_formatted'])->toContain('150,00');
});

it('renders transaction direction/status/counterparty relative to the viewer', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    Wallet::factory()->forUser($alice)->create();
    Wallet::factory()->forUser($bob)->create();
    app(DepositFunds::class)(new DepositData(userId: $alice->id, amount: Money::fromCents(10000)));
    $transfer = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));
    $transfer->load('sourceWallet.user', 'destinationWallet.user');

    $forBob = (new TransactionResource($transfer))->toArray(requestAs($bob));
    expect($forBob)->toMatchArray(['type' => 'transfer', 'status' => 'completed', 'direction' => 'in']);
    expect($forBob['counterparty']['name'])->toBe($alice->name);

    $forAlice = (new TransactionResource($transfer))->toArray(requestAs($alice));
    expect($forAlice['direction'])->toBe('out');
    expect($forAlice['counterparty']['name'])->toBe($bob->name);
});

it('renders a deposit counterparty as Deposito', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    $deposit = app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));
    $deposit->load('sourceWallet.user', 'destinationWallet.user');

    $data = (new TransactionResource($deposit))->toArray(requestAs($user));
    expect($data)->toMatchArray(['type' => 'deposit', 'direction' => 'in']);
    expect($data['counterparty'])->toBe(['label' => 'Depósito']);
});

it('renders the ledger entry resource shape', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(7500)));

    $entry = $user->wallet()->first()->ledgerEntries()->with('transaction')->orderByDesc('sequence')->first();
    $data = (new LedgerEntryResource($entry))->toArray(requestAs($user));

    expect($data)->toMatchArray([
        'direction' => 'credit',
        'amount' => '75.00',
        'balance_after' => '75.00',
        'balance_after_cents' => 7500,
        'sequence' => 1,
    ]);
    expect($data['transaction']['type'])->toBe('deposit');
});
