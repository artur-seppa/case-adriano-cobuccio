<?php

namespace App\Domain\Wallet\Policies;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * A user may see a transaction when they initiated it or own one of the
     * two wallets it moved money between.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        $transaction->loadMissing('sourceWallet:id,user_id', 'destinationWallet:id,user_id');

        return $transaction->initiator_id === $user->id
            || $transaction->sourceWallet?->user_id === $user->id
            || $transaction->destinationWallet?->user_id === $user->id;
    }

    /**
     * A user may reverse only a transaction they initiated, that is not itself a
     * reversal, and that has not already been reversed. The ReverseTransaction
     * action re-validates all of this authoritatively under lock.
     */
    public function reverse(User $user, Transaction $transaction): bool
    {
        return $transaction->initiator_id === $user->id
            && $transaction->type !== TransactionType::Reversal
            && ! $transaction->isReversed();
    }
}
