<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\Log;

it('logs a structured funds.deposited line when a deposit happens', function () {
    $lines = [];
    Log::listen(function ($e) use (&$lines) {
        if (str_starts_with($e->message, 'funds.') || str_starts_with($e->message, 'transaction.')) {
            $lines[] = ['msg' => $e->message, 'ctx' => $e->context];
        }
    });

    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(10000)));

    $deposited = collect($lines)->firstWhere('msg', 'funds.deposited');
    expect($deposited)->not->toBeNull()
        ->and($deposited['ctx'])->toHaveKeys(['transaction_id', 'amount_cents', 'currency']);
});
