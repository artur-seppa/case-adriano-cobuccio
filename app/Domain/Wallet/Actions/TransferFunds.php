<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Exceptions\CurrencyMismatchException;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\TransactionCouldNotCompleteException;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\LedgerPoster;
use App\Support\Metrics;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final class TransferFunds
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly LedgerPoster $ledgerPoster,
    ) {}

    public function __invoke(TransferData $data): Transaction
    {
        if ($data->senderId === $data->recipientId) {
            throw new \InvalidArgumentException('Sender and recipient must differ.');
        }

        $senderWallet = Wallet::where('user_id', $data->senderId)->firstOrFail();
        $recipientWallet = Wallet::where('user_id', $data->recipientId)->firstOrFail();

        if ($senderWallet->currency !== $recipientWallet->currency
            || $data->amount->currency() !== $senderWallet->currency) {
            throw new CurrencyMismatchException($senderWallet->currency, $data->amount->currency());
        }

        if ($data->idempotencyKey !== null
            && ($existing = Transaction::where('idempotency_key', $data->idempotencyKey)->first())) {
            return $existing;
        }

        try {
            $transaction = $this->db->transaction(function () use ($data, $senderWallet, $recipientWallet) {
                $ids = collect([$senderWallet->id, $recipientWallet->id])->sort()->values();
                $wallets = Wallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $sender = $wallets[$senderWallet->id];
                $recipient = $wallets[$recipientWallet->id];

                if (! $sender->balance->greaterThanOrEqual($data->amount)) {
                    throw new InsufficientFundsException($sender->balance, $data->amount);
                }

                return $this->ledgerPoster->post(new LedgerPosting(
                    type: TransactionType::Transfer,
                    initiatorId: $data->senderId,
                    legs: [
                        new PostingLeg($sender, EntryDirection::Debit, $data->amount),
                        new PostingLeg($recipient, EntryDirection::Credit, $data->amount),
                    ],
                    idempotencyKey: $data->idempotencyKey,
                    description: $data->description,
                    metadata: $data->metadata,
                ));
            }, attempts: 3);
        } catch (QueryException $e) {
            throw new TransactionCouldNotCompleteException(3, previous: $e);
        } catch (InsufficientFundsException $e) {
            Metrics::counter('insufficient_funds_total', 'Transfers rejected for insufficient funds.', [], []);

            throw $e;
        }

        FundsTransferred::dispatch($transaction);

        return $transaction;
    }
}
