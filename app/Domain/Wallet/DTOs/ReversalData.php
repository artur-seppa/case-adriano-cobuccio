<?php

namespace App\Domain\Wallet\DTOs;

use App\Domain\Wallet\Enums\ReversalReason;

final readonly class ReversalData
{
    public function __construct(
        public string $transactionId,
        public ReversalReason $reason,
        public ?string $initiatedByUserId = null,
        public ?string $note = null,
        public array $metadata = [],
    ) {}
}
