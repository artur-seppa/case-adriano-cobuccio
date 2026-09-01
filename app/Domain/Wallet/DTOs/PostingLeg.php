<?php

namespace App\Domain\Wallet\DTOs;

use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;

final readonly class PostingLeg
{
    public function __construct(
        public Wallet $wallet,
        public EntryDirection $direction,
        public Money $amount,
    ) {}
}
