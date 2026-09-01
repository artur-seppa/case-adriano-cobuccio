<?php

namespace App\Domain\Wallet\Exceptions;

final class CannotReverseReversalException extends WalletDomainException
{
    public function __construct(private readonly string $transactionId)
    {
        parent::__construct("Transaction {$transactionId} is a reversal and cannot be reversed.");
    }

    public function context(): array
    {
        return ['transaction_id' => $this->transactionId];
    }
}
