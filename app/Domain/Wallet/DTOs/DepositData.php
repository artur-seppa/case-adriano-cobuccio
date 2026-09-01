<?php

namespace App\Domain\Wallet\DTOs;

use App\Domain\Wallet\ValueObjects\Money;

final readonly class DepositData
{
    public function __construct(
        public string $userId,
        public Money $amount,
        public ?string $idempotencyKey = null,
        public ?string $description = null,
        public ?string $fundingMethod = null,
        public array $metadata = [],
    ) {}
}
