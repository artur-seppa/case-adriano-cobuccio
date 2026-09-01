<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Exceptions\CurrencyMismatchException;
use App\Domain\Wallet\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Wallet\Exceptions\UnbalancedLedgerException;
use App\Domain\Wallet\Models\LedgerEntry;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class LedgerPoster
{
    /**
     * Precondition: every leg's wallet row is already locked (lockForUpdate) by the caller,
     * and the caller has opened the outer transaction — LedgerPoster does not open it and does
     * not lock wallets. It opens a nested savepoint around the `Transaction` insert only, so a
     * unique-constraint violation can be recovered (idempotency Layer B) without poisoning the
     * caller's transaction on Postgres.
     */
    public function post(LedgerPosting $posting): Transaction
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('LedgerPoster::post() must run inside a caller-opened transaction.');
        }

        $this->assertShape($posting);

        $debitLeg = collect($posting->legs)->firstWhere('direction', EntryDirection::Debit);
        $creditLeg = collect($posting->legs)->firstWhere('direction', EntryDirection::Credit);
        $currency = $posting->legs[0]->amount->currency();

        try {
            // Wrapped in a SAVEPOINT (nested transaction): on Postgres a unique
            // violation aborts the enclosing transaction, so we roll back to the
            // savepoint before reading the winning row in resolveUniqueViolation().
            $transaction = DB::transaction(fn () => Transaction::create([
                'type' => $posting->type,
                'initiator_id' => $posting->initiatorId,
                'source_wallet_id' => $debitLeg->wallet->id,
                'destination_wallet_id' => $creditLeg->wallet->id,
                'amount_cents' => $posting->legs[0]->amount->cents(),
                'currency' => $currency,
                'reversal_of_transaction_id' => $posting->reversalOf,
                'reversal_reason' => $posting->reversalReason,
                'idempotency_key' => $posting->idempotencyKey,
                'description' => $posting->description,
                'metadata' => $posting->metadata,
            ]));
        } catch (UniqueConstraintViolationException $e) {
            return $this->resolveUniqueViolation($e, $posting);
        }

        foreach ($posting->legs as $leg) {
            $wallet = $leg->wallet;
            $delta = $leg->direction === EntryDirection::Credit
                ? $leg->amount->cents()
                : -$leg->amount->cents();

            $wallet->entry_count += 1;
            $wallet->balance_cents += $delta;
            $wallet->save();

            LedgerEntry::create([
                'transaction_id' => $transaction->id,
                'wallet_id' => $wallet->id,
                'direction' => $leg->direction,
                'amount_cents' => $leg->amount->cents(),
                'currency' => $currency,
                'balance_after_cents' => $wallet->balance_cents,
                'sequence' => $wallet->entry_count,
            ]);
        }

        $this->assertBalanced($transaction);

        return $transaction;
    }

    private function assertShape(LedgerPosting $posting): void
    {
        if (count($posting->legs) < 2) {
            throw new UnbalancedLedgerException('(pending)', 0);
        }

        $currencies = array_values(array_unique(array_map(fn ($l) => $l->amount->currency(), $posting->legs)));
        if (count($currencies) > 1) {
            throw new CurrencyMismatchException($currencies[0], $currencies[1]);
        }

        $imbalance = array_sum(array_map(
            fn ($l) => $l->direction === EntryDirection::Credit ? $l->amount->cents() : -$l->amount->cents(),
            $posting->legs,
        ));

        if ($imbalance !== 0) {
            throw new UnbalancedLedgerException('(pending)', $imbalance);
        }

        $hasDebit = collect($posting->legs)->contains(fn ($l) => $l->direction === EntryDirection::Debit);
        $hasCredit = collect($posting->legs)->contains(fn ($l) => $l->direction === EntryDirection::Credit);
        if (! $hasDebit || ! $hasCredit) {
            throw new UnbalancedLedgerException('(pending)', $imbalance);
        }
    }

    private function assertBalanced(Transaction $transaction): void
    {
        $imbalance = (int) $transaction->entries()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_cents ELSE -amount_cents END), 0) AS imbalance")
            ->value('imbalance');

        if ($imbalance !== 0) {
            throw new UnbalancedLedgerException($transaction->id, $imbalance);
        }
    }

    private function resolveUniqueViolation(UniqueConstraintViolationException $e, LedgerPosting $posting): Transaction
    {
        if ($posting->idempotencyKey !== null && str_contains($e->getMessage(), 'transactions_idempotency_key_unique')) {
            return Transaction::where('idempotency_key', $posting->idempotencyKey)->firstOrFail();
        }

        if ($posting->reversalOf !== null && str_contains($e->getMessage(), 'transactions_reversal_of_unique')) {
            $existing = Transaction::where('reversal_of_transaction_id', $posting->reversalOf)->firstOrFail();
            throw new TransactionAlreadyReversedException($posting->reversalOf, $existing->id);
        }

        throw $e;
    }
}
