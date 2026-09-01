<?php

namespace App\Domain\Wallet\Exceptions;

final class TransactionCouldNotCompleteException extends WalletDomainException
{
    public function __construct(private readonly int $attempts, ?\Throwable $previous = null)
    {
        parent::__construct("Transaction could not complete after {$attempts} attempts.", 0, $previous);
    }

    public function context(): array
    {
        return ['attempts' => $this->attempts];
    }
}
