<?php

namespace App\Domain\Wallet\Enums;

enum ReversalReason: string
{
    case Inconsistency = 'inconsistency';
    case UserRequest = 'user_request';
    case Fraud = 'fraud';
    case Duplicate = 'duplicate';
}
