<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Events\FundsDeposited;
use App\Domain\Wallet\Exceptions\CurrencyMismatchException;
use App\Domain\Wallet\Exceptions\TransactionCouldNotCompleteException;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\LedgerPoster;
use App\Domain\Wallet\Support\SystemWallets;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final class DepositFunds
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly LedgerPoster $ledgerPoster,
    ) {}

    public function __invoke(DepositData $data): Transaction
    {
        $userWallet = Wallet::where('user_id', $data->userId)->firstOrFail();
        $system = SystemWallets::externalWorld();

        if ($data->amount->currency() !== $userWallet->currency) {
            throw new CurrencyMismatchException($userWallet->currency, $data->amount->currency());
        }

        if ($data->idempotencyKey !== null
            && ($existing = Transaction::where('idempotency_key', $data->idempotencyKey)->first())) {
            return $existing;
        }

        try {
            $transaction = $this->db->transaction(function () use ($data, $userWallet, $system) {
                $ids = collect([$userWallet->id, $system->id])->sort()->values();
                $wallets = Wallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                return $this->ledgerPoster->post(new LedgerPosting(
                    type: TransactionType::Deposit,
                    initiatorId: $data->userId,
                    legs: [
                        new PostingLeg($wallets[$system->id], EntryDirection::Debit, $data->amount),
                        new PostingLeg($wallets[$userWallet->id], EntryDirection::Credit, $data->amount),
                    ],
                    idempotencyKey: $data->idempotencyKey,
                    description: $data->description,
                    metadata: $data->metadata + array_filter(['funding_method' => $data->fundingMethod]),
                ));
            }, attempts: 3);
        } catch (QueryException $e) {
            throw new TransactionCouldNotCompleteException(3, previous: $e);
        }

        FundsDeposited::dispatch($transaction);

        return $transaction;
    }
}
