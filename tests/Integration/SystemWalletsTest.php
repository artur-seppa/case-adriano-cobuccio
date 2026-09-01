<?php

use App\Domain\Wallet\Enums\WalletType;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Support\SystemWallets;

it('creates external_world once and is idempotent', function () {
    $a = SystemWallets::externalWorld();
    $b = SystemWallets::externalWorld();

    expect($a->is($b))->toBeTrue()
        ->and($a->type)->toBe(WalletType::System)
        ->and($a->reference)->toBe('external_world')
        ->and(Wallet::where('reference', 'external_world')->count())->toBe(1);
});
