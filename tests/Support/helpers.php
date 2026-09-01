<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\SystemWallets;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! function_exists('systemWallet')) {
    function systemWallet(): Wallet
    {
        return SystemWallets::externalWorld();
    }
}

if (! function_exists('lockedPair')) {
    function lockedPair(string $aId, string $bId): array
    {
        $ids = collect([$aId, $bId])->sort()->values()->all();
        $wallets = Wallet::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        return [$wallets[$aId], $wallets[$bId]];
    }
}

if (! function_exists('giveWallet')) {
    function giveWallet(User $user, int $cents = 0): Wallet
    {
        $wallet = Wallet::factory()->forUser($user)->create();

        if ($cents > 0) {
            app(DepositFunds::class)(
                new DepositData(
                    userId: $user->id,
                    amount: Money::fromCents($cents),
                ),
            );
            $wallet->refresh();
        }

        return $wallet;
    }
}

if (! function_exists('transferBetween')) {
    function transferBetween(User $from, User $to, int $cents): Transaction
    {
        return app(TransferFunds::class)(
            new TransferData(
                senderId: $from->id,
                recipientId: $to->id,
                amount: Money::fromCents($cents),
            ),
        );
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

        $systemId = SystemWallets::externalWorld()->id;

        return ['user' => $userId, 'wallet' => $walletId, 'system' => $systemId];
    }
}

// transferBetween() intentionally omitted at CP4 — it depends on TransferFunds
// (App\Domain\Wallet\Actions\TransferFunds), which arrives at CP7. Nothing in
// CP4 needs it, so it will be added alongside that action.
