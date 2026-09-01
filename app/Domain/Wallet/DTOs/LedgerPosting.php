<?php

namespace App\Domain\Wallet\DTOs;

use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;

final readonly class LedgerPosting
{
    /** @param  PostingLeg[]  $legs */
    public function __construct(
        public TransactionType $type,
        public ?string $initiatorId,
        public array $legs,
        public ?string $reversalOf = null,
        public ?ReversalReason $reversalReason = null,
        public ?string $idempotencyKey = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
