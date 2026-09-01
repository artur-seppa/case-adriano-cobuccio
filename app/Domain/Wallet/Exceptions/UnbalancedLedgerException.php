<?php

namespace App\Domain\Wallet\Exceptions;

final class UnbalancedLedgerException extends WalletDomainException
{
    public function __construct(
        private readonly string $transactionId,
        private readonly int $imbalanceCents,
    ) {
        parent::__construct("Ledger imbalance for transaction {$transactionId}: {$imbalanceCents}.");
    }

    public function context(): array
    {
        return ['transaction_id' => $this->transactionId, 'imbalance_cents' => $this->imbalanceCents];
    }
}
