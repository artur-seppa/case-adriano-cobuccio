<?php

namespace App\Domain\Wallet\Exceptions;

use App\Domain\Wallet\ValueObjects\Money;

final class InsufficientFundsException extends WalletDomainException
{
    public function __construct(
        private readonly Money $available,
        private readonly Money $requested,
    ) {
        parent::__construct('Wallet has insufficient funds for this transfer.');
    }

    public function context(): array
    {
        return [
            'available' => $this->available->decimalString(),
            'requested' => $this->requested->decimalString(),
            'currency' => $this->available->currency(),
        ];
    }
}
