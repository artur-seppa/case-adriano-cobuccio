<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! function_exists('systemWallet')) {
    function systemWallet(): Wallet
    {
        return Wallet::firstOrCreate(
            ['reference' => 'external_world'],
            Wallet::factory()->system()->raw(),
        );
    }
}

if (! function_exists('giveWallet')) {
    function giveWallet(User $user, int $cents = 0): Wallet
    {
        $wallet = Wallet::factory()->forUser($user)->create();

        if ($cents > 0) {
            // TODO(CP7): replace with app(DepositFunds::class)(new DepositData(...))
            $wallet->update(['balance_cents' => $cents]);
        }

        return $wallet;
    }
}

if (! function_exists('seedWalletFixtures')) {
    function seedWalletFixtures(): array
    {
        $userId = (string) Str::ulid();
        DB::table('users')->insert([
            'id' => $userId, 'name' => 'N', 'email' => 'n@example.test',
            'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $walletId = (string) Str::ulid();
        DB::table('wallets')->insert([
            'id' => $walletId, 'type' => 'user', 'user_id' => $userId,
            'reference' => null, 'currency' => 'BRL', 'balance_cents' => 0, 'entry_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $systemId = (string) Str::ulid();
        DB::table('wallets')->insert([
            'id' => $systemId, 'type' => 'system', 'user_id' => null,
            'reference' => 'external_world', 'currency' => 'BRL', 'balance_cents' => 0, 'entry_count' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['user' => $userId, 'wallet' => $walletId, 'system' => $systemId];
    }
}

// transferBetween() intentionally omitted at CP4 — it depends on TransferFunds
// (App\Domain\Wallet\Actions\TransferFunds), which arrives at CP7. Nothing in
// CP4 needs it, so it will be added alongside that action.
