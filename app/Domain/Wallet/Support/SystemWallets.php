<?php

namespace App\Domain\Wallet\Support;

use App\Domain\Wallet\Enums\WalletType;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Str;

final class SystemWallets
{
    public const EXTERNAL_WORLD = 'external_world';

    public static function externalWorld(): Wallet
    {
        return Wallet::firstOrCreate(
            ['reference' => self::EXTERNAL_WORLD],
            [
                'id' => (string) Str::ulid(),
                'type' => WalletType::System,
                'currency' => 'BRL',
                'balance_cents' => 0,
                'entry_count' => 0,
            ],
        );
    }
}
