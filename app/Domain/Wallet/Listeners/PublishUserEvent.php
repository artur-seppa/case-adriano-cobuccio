<?php

namespace App\Domain\Wallet\Listeners;

use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Support\Facades\Redis;

/**
 * Synchronous, post-commit (the Actions dispatch these events via
 * DB::afterCommit): pushes a compact "something moved, refetch" nudge onto the
 * affected user's Redis channel, which the SSE stream forwards. Never carries
 * authoritative numbers — the client always refetches.
 */
class PublishUserEvent
{
    public function handleDeposited(FundsDeposited $event): void
    {
        $this->publish($event->transaction->destinationWallet->user_id, 'deposit.completed', $event->transaction, 'in');
    }

    public function handleTransferred(FundsTransferred $event): void
    {
        $this->publish($event->transaction->sourceWallet->user_id, 'transaction.sent', $event->transaction, 'out');
        $this->publish($event->transaction->destinationWallet->user_id, 'transaction.received', $event->transaction, 'in');
    }

    public function handleReversed(TransactionReversed $event): void
    {
        $this->publish($event->reversal->sourceWallet->user_id, 'transaction.reversed', $event->reversal, 'out');
        $this->publish($event->reversal->destinationWallet->user_id, 'transaction.reversed', $event->reversal, 'in');
    }

    private function publish(?string $userId, string $type, Transaction $transaction, string $direction): void
    {
        if ($userId === null) {
            return; // system wallet (external_world) has no user to notify
        }

        Redis::publish("user-events:{$userId}", json_encode([
            'type' => $type,
            'transaction_id' => $transaction->id,
            'direction' => $direction,
            'amount_formatted' => $transaction->amount->formatBRL(),
            'at' => now()->toIso8601String(),
        ]));
    }
}
