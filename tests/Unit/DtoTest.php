<?php

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

it('holds transfer input immutably', function () {
    $dto = new TransferData(
        senderId: 'u1', recipientId: 'u2', amount: Money::fromCents(5000), description: 'lunch',
    );

    expect($dto->senderId)->toBe('u1')
        ->and($dto->amount->cents())->toBe(5000)
        ->and($dto->idempotencyKey)->toBeNull();
});

it('builds a posting from legs', function () {
    $a = Wallet::factory()->forUser(User::factory()->create())->make();
    $b = Wallet::factory()->system()->make();

    $posting = new LedgerPosting(
        type: TransactionType::Transfer,
        initiatorId: 'u1',
        legs: [
            new PostingLeg($a, EntryDirection::Debit, Money::fromCents(100)),
            new PostingLeg($b, EntryDirection::Credit, Money::fromCents(100)),
        ],
    );

    expect($posting->legs)->toHaveCount(2)
        ->and($posting->legs[0]->direction)->toBe(EntryDirection::Debit)
        ->and($posting->reversalOf)->toBeNull();
});
