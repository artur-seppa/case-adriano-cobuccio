<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Exceptions\CannotReverseReversalException;
use App\Domain\Wallet\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Wallet\Exceptions\TransactionCouldNotCompleteException;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\LedgerPoster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final class ReverseTransaction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly LedgerPoster $ledgerPoster,
    ) {}

    public function __invoke(ReversalData $data): Transaction
    {
        $original = Transaction::findOrFail($data->transactionId);

        if ($original->type === TransactionType::Reversal) {
            throw new CannotReverseReversalException($original->id);
        }

        if ($original->reversalTransaction()->exists()) {
            throw new TransactionAlreadyReversedException($original->id, $original->reversalTransaction->id);
        }

        if ($data->reason === ReversalReason::UserRequest
            && $data->initiatedByUserId !== $original->initiator_id) {
            throw new AuthorizationException('Only the initiator can reverse this transaction.');
        }

        try {
            $reversal = $this->db->transaction(function () use ($data, $original) {
                $ids = collect([$original->source_wallet_id, $original->destination_wallet_id])->sort()->values();
                $wallets = Wallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $lockedOriginal = Transaction::whereKey($original->id)->lockForUpdate()->firstOrFail();
                if ($lockedOriginal->reversalTransaction()->exists()) {
                    throw new TransactionAlreadyReversedException(
                        $lockedOriginal->id,
                        $lockedOriginal->reversalTransaction->id,
                    );
                }

                $wasDebited = $wallets[$original->source_wallet_id];
                $wasCredited = $wallets[$original->destination_wallet_id];

                return $this->ledgerPoster->post(new LedgerPosting(
                    type: TransactionType::Reversal,
                    initiatorId: $data->initiatedByUserId,
                    legs: [
                        new PostingLeg($wasDebited, EntryDirection::Credit, $original->amount),
                        new PostingLeg($wasCredited, EntryDirection::Debit, $original->amount),
                    ],
                    reversalOf: $original->id,
                    reversalReason: $data->reason,
                    description: $data->note,
                    metadata: $data->metadata + ['reversed_by' => $data->initiatedByUserId ?? 'system'],
                ));
            }, attempts: 3);
        } catch (QueryException $e) {
            throw new TransactionCouldNotCompleteException(3, previous: $e);
        }

        TransactionReversed::dispatch($reversal, $original);

        return $reversal;
    }
}
