<?php

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\LedgerEntry;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\LedgerPoster;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('returns the existing transaction when the idempotency key repeats (Layer B)', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->create();
    $system = systemWallet();
    $key = (string) Str::uuid();

    $post = fn () => DB::transaction(function () use ($wallet, $system, $key) {
        [$w, $s] = lockedPair($wallet->id, $system->id);

        return app(LedgerPoster::class)->post(new LedgerPosting(
            type: TransactionType::Deposit,
            initiatorId: $w->user_id,
            legs: [
                new PostingLeg($s, EntryDirection::Debit, Money::fromCents(100)),
                new PostingLeg($w, EntryDirection::Credit, Money::fromCents(100)),
            ],
            idempotencyKey: $key,
        ));
    });

    $first = $post();
    $second = $post();

    expect($second->id)->toBe($first->id)
        ->and(Transaction::where('idempotency_key', $key)->count())->toBe(1)
        ->and($wallet->fresh()->balance_cents)->toBe(100)
        ->and($wallet->fresh()->entry_count)->toBe(1)
        ->and(LedgerEntry::count())->toBe(2);
});
