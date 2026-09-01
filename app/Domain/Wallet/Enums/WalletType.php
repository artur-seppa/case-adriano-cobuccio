<?php

namespace App\Domain\Wallet\Enums;

enum WalletType: string
{
    case User = 'user';
    case System = 'system';
}
