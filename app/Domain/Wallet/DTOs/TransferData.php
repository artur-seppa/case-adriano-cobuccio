<?php

namespace App\Domain\Wallet\DTOs;

use App\Domain\Wallet\ValueObjects\Money;

final readonly class TransferData
{
    public function __construct(
        public string $senderId,
        public string $recipientId,
        public Money $amount,
        public ?string $idempotencyKey = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
