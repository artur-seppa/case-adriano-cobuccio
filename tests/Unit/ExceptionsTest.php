<?php

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Wallet\Exceptions\WalletDomainException;
use App\Domain\Wallet\ValueObjects\Money;

it('carries structured context', function () {
    $e = new InsufficientFundsException(Money::fromCents(1000), Money::fromCents(5000));

    expect($e)->toBeInstanceOf(WalletDomainException::class)
        ->and($e->context())->toBe(['available' => '10.00', 'requested' => '50.00', 'currency' => 'BRL']);

    $r = new TransactionAlreadyReversedException('t1', 't2');
    expect($r->context())->toBe(['transaction_id' => 't1', 'reversed_by_transaction_id' => 't2']);
});
