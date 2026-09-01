<?php

namespace App\Domain\Wallet\Exceptions;

final class TransactionAlreadyReversedException extends WalletDomainException
{
    public function __construct(
        private readonly string $transactionId,
        private readonly string $reversedByTransactionId,
    ) {
        parent::__construct("Transaction {$transactionId} is already reversed.");
    }

    public function context(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'reversed_by_transaction_id' => $this->reversedByTransactionId,
        ];
    }
}
