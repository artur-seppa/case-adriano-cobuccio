<?php

namespace App\Domain\Wallet\Events;

use App\Domain\Wallet\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;

final class FundsTransferred
{
    use Dispatchable;

    public function __construct(public Transaction $transaction) {}
}
