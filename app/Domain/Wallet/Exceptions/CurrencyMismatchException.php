<?php

namespace App\Domain\Wallet\Exceptions;

final class CurrencyMismatchException extends WalletDomainException
{
    public function __construct(
        private readonly string $expected,
        private readonly string $got,
    ) {
        parent::__construct("Currency mismatch: expected {$expected}, got {$got}.");
    }

    public function context(): array
    {
        return ['expected' => $this->expected, 'got' => $this->got];
    }
}
