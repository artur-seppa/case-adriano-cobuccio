<?php

namespace App\Domain\Wallet\Listeners;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Models\Transaction;
use App\Support\Metrics;
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
        $reason = $event->reversal->reversal_reason?->value;

        $this->log('transaction.reversed', $event->reversal, [
            'reason' => $reason,
        ]);

        Metrics::counter('reversals_total', 'Reversals by reason.', ['reason'], [$reason ?? 'unspecified']);
    }

    private function log(string $event, Transaction $transaction, array $extra = []): void
    {
        Log::info($event, array_merge([
            'transaction_id' => $transaction->id,
            'type' => $transaction->type->value,
            'amount_cents' => $transaction->amount_cents,
            'currency' => $transaction->currency,
        ], $extra));

        // Every logged transaction is, by construction, a completed one: this
        // domain never persists a partial/failed transaction row (Plan 1's
        // ledger design is all-or-nothing per Action).
        Metrics::counter(
            'transactions_total', 'Wallet transactions by type and status.',
            ['type', 'status'], [$transaction->type->value, 'completed'],
        );
        Metrics::histogram(
            'transaction_amount_cents', 'Transaction amount distribution, in cents.',
            (float) $transaction->amount_cents, ['type'], [$transaction->type->value],
            [100, 1000, 10000, 100000, 1000000],
        );
    }
}
