<?php

use App\Domain\Wallet\DTOs\LedgerPosting;
use App\Domain\Wallet\DTOs\PostingLeg;
use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Exceptions\UnbalancedLedgerException;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\LedgerPoster;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('posts a balanced 2-leg transaction and updates balances, sequence and snapshots', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->create();
    $system = systemWallet();

    $tx = DB::transaction(function () use ($wallet, $system) {
        [$w, $s] = lockedPair($wallet->id, $system->id);

        return app(LedgerPoster::class)->post(new LedgerPosting(
            type: TransactionType::Deposit,
            initiatorId: $w->user_id,
            legs: [
                new PostingLeg($s, EntryDirection::Debit, Money::fromCents(15000)),
                new PostingLeg($w, EntryDirection::Credit, Money::fromCents(15000)),
            ],
        ));
    });

    expect($tx->type)->toBe(TransactionType::Deposit)
        ->and($tx->source_wallet_id)->toBe($system->id)
        ->and($tx->destination_wallet_id)->toBe($wallet->id)
        ->and($tx->amount_cents)->toBe(15000);

    expect($wallet->fresh()->balance_cents)->toBe(15000)
        ->and($wallet->fresh()->entry_count)->toBe(1)
        ->and($system->fresh()->balance_cents)->toBe(-15000)
        ->and($system->fresh()->entry_count)->toBe(1);

    expect($tx->entries()->count())->toBe(2);

    $credit = $tx->entries()->where('wallet_id', $wallet->id)->first();
    expect($credit->direction)->toBe(EntryDirection::Credit)
        ->and($credit->sequence)->toBe(1)
        ->and($credit->balance_after_cents)->toBe(15000);

    $debit = $tx->entries()->where('wallet_id', $system->id)->first();
    expect($debit->direction)->toBe(EntryDirection::Debit)
        ->and($debit->sequence)->toBe(1)
        ->and($debit->balance_after_cents)->toBe(-15000);
});

it('rejects an unbalanced posting before touching the DB', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->forUser($user)->make();
    $system = Wallet::factory()->system()->make();

    $call = fn () => DB::transaction(fn () => app(LedgerPoster::class)->post(new LedgerPosting(
        type: TransactionType::Deposit,
        initiatorId: null,
        legs: [
            new PostingLeg($system, EntryDirection::Debit, Money::fromCents(100)),
            new PostingLeg($wallet, EntryDirection::Credit, Money::fromCents(90)),
        ],
    )));

    expect($call)->toThrow(UnbalancedLedgerException::class);

    try {
        $call();
    } catch (UnbalancedLedgerException $e) {
        expect($e->context()['imbalance_cents'])->toBe(-10);
    }

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('ledger_entries', 0);
});
