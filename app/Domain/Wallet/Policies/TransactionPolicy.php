<?php

namespace App\Domain\Wallet\Policies;

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
     * Authorization is ownership only: a user may only ask to reverse a
     * transaction they initiated. Whether that transaction can actually be
     * reversed (not already reversed → 409, not itself a reversal → 422) is a
     * business rule the ReverseTransaction action enforces authoritatively under
     * lock, so those cases surface as their proper domain errors, not a 403.
     */
    public function reverse(User $user, Transaction $transaction): bool
    {
        return $transaction->initiator_id === $user->id;
    }
}
