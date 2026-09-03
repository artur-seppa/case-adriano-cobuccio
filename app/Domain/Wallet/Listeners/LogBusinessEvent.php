<?php

namespace App\Domain\Wallet\Listeners;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Log estruturado de evento de negócio. Síncrono, ao lado do PublishUserEvent
 * (mesmo evento, responsabilidade separada). Nunca enfileirar.
 */
class LogBusinessEvent
{
    public function handleDeposited(FundsDeposited $event): void
    {
        $this->log('funds.deposited', $event->transaction);
    }

    public function handleTransferred(FundsTransferred $event): void
    {
        $this->log('funds.transferred', $event->transaction, [
            'from' => $event->transaction->sourceWallet->user_id,
            'to' => $event->transaction->destinationWallet->user_id,
        ]);
    }

    public function handleReversed(TransactionReversed $event): void
    {
        $this->log('transaction.reversed', $event->reversal, [
            'reason' => $event->reversal->reversal_reason?->value,
        ]);
    }

    private function log(string $event, Transaction $transaction, array $extra = []): void
    {
        Log::info($event, array_merge([
            'transaction_id' => $transaction->id,
            'type' => $transaction->type->value,
            'amount_cents' => $transaction->amount_cents,
            'currency' => $transaction->currency,
        ], $extra));
    }
}
