<?php

namespace App\Domain\Wallet\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Transfer = 'transfer';
    case Reversal = 'reversal';
}
