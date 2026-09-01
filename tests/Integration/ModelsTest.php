<?php

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;

it('casts wallet balance to Money and keeps enum type', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->withBalanceCents(15000)->create();

    expect($wallet->balance)->toBeInstanceOf(Money::class)
        ->and($wallet->balance->cents())->toBe(15000)
        ->and($wallet->type->value)->toBe('user')
        ->and($wallet->user->is($user))->toBeTrue();
});

it('exposes reversal linkage and isReversed()', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->create();
    $system = Wallet::factory()->system()->create();

    $original = Transaction::factory()->deposit()->create([
        'source_wallet_id' => $system->id, 'destination_wallet_id' => $wallet->id, 'amount_cents' => 100,
    ]);
    expect($original->isReversed())->toBeFalse();

    $reversal = Transaction::factory()->reversal()->create([
        'source_wallet_id' => $wallet->id, 'destination_wallet_id' => $system->id, 'amount_cents' => 100,
        'reversal_of_transaction_id' => $original->id, 'reversal_reason' => 'user_request',
    ]);

    expect($original->fresh()->isReversed())->toBeTrue()
        ->and($original->fresh()->reversalTransaction->is($reversal))->toBeTrue()
        ->and($reversal->type)->toBe(TransactionType::Reversal);
});
