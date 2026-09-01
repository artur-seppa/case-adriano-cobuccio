<?php

use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Enums\WalletType;

it('has the expected backing values', function () {
    expect(TransactionType::Deposit->value)->toBe('deposit')
        ->and(TransactionType::Transfer->value)->toBe('transfer')
        ->and(TransactionType::Reversal->value)->toBe('reversal')
        ->and(ReversalReason::UserRequest->value)->toBe('user_request')
        ->and(EntryDirection::Debit->value)->toBe('debit')
        ->and(WalletType::System->value)->toBe('system');
});

it('flips direction', function () {
    expect(EntryDirection::Debit->opposite())->toBe(EntryDirection::Credit)
        ->and(EntryDirection::Credit->opposite())->toBe(EntryDirection::Debit);
});
