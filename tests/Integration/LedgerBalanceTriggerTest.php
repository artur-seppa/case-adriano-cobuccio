<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// seedWalletFixtures() lives in tests/Support/helpers.php so Integration files
// can share it without a file-scope redeclaration clash.

it('rejects an unbalanced transaction at commit time', function () {
    ['user' => $userId, 'wallet' => $walletId, 'system' => $systemId] = seedWalletFixtures();

    $attempt = function () use ($userId, $walletId, $systemId) {
        DB::transaction(function () use ($userId, $walletId, $systemId) {
            $txId = (string) Str::ulid();
            DB::table('transactions')->insert([
                'id' => $txId, 'type' => 'deposit', 'initiator_id' => $userId,
                'source_wallet_id' => $systemId, 'destination_wallet_id' => $walletId,
                'amount_cents' => 100, 'currency' => 'BRL', 'metadata' => '{}', 'created_at' => now(),
            ]);
            // only ONE leg -> imbalance of -100
            DB::table('ledger_entries')->insert([
                'id' => (string) Str::ulid(), 'transaction_id' => $txId, 'wallet_id' => $walletId,
                'direction' => 'credit', 'amount_cents' => 100, 'currency' => 'BRL',
                'balance_after_cents' => 100, 'sequence' => 1, 'created_at' => now(),
            ]);
        });
    };

    // The deferred CONSTRAINT TRIGGER fires at COMMIT. Laravel's
    // Connection::commit() calls PDO::commit() directly and does not wrap a
    // commit-time failure, so it surfaces as a raw PDOException
    // (QueryException's parent class).
    expect($attempt)->toThrow(PDOException::class);
});

it('accepts a balanced transaction', function () {
    ['user' => $userId, 'wallet' => $walletId, 'system' => $systemId] = seedWalletFixtures();

    DB::transaction(function () use ($userId, $walletId, $systemId) {
        $txId = (string) Str::ulid();
        DB::table('transactions')->insert([
            'id' => $txId, 'type' => 'deposit', 'initiator_id' => $userId,
            'source_wallet_id' => $systemId, 'destination_wallet_id' => $walletId,
            'amount_cents' => 100, 'currency' => 'BRL', 'metadata' => '{}', 'created_at' => now(),
        ]);
        DB::table('ledger_entries')->insert([
            ['id' => (string) Str::ulid(), 'transaction_id' => $txId, 'wallet_id' => $systemId,
                'direction' => 'debit', 'amount_cents' => 100, 'currency' => 'BRL',
                'balance_after_cents' => -100, 'sequence' => 1, 'created_at' => now()],
            ['id' => (string) Str::ulid(), 'transaction_id' => $txId, 'wallet_id' => $walletId,
                'direction' => 'credit', 'amount_cents' => 100, 'currency' => 'BRL',
                'balance_after_cents' => 100, 'sequence' => 1, 'created_at' => now()],
        ]);
    });

    expect(DB::table('ledger_entries')->count())->toBe(2);
});
