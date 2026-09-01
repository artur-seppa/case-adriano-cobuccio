<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('returns the current wallet', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(15000)));

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('data.balance', '150.00')
        ->assertJsonPath('data.balance_cents', 15000)
        ->assertJsonPath('data.currency', 'BRL')
        ->assertHeader('X-Request-Id');
});

it('401s without a session', function () {
    $this->getJson('/api/v1/wallet')
        ->assertStatus(401)
        ->assertJsonPath('type', 'https://wallet.test/problems/unauthenticated');
});

it('paginates the statement by cursor, newest sequence first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Wallet::factory()->forUser($user)->create();
    Wallet::factory()->forUser($other)->create();
    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(50000)));
    foreach (range(1, 4) as $i) {
        app(TransferFunds::class)(new TransferData(senderId: $user->id, recipientId: $other->id, amount: Money::fromCents(1000)));
    }

    $res = $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet/statement?per_page=3')
        ->assertOk()
        ->assertJsonStructure(['data' => [['sequence', 'direction', 'amount', 'balance_after']], 'links', 'meta']);

    $sequences = collect($res->json('data'))->pluck('sequence')->all();
    expect($sequences)->toBe([5, 4, 3]); // 1 deposit + 4 transfers = 5 entries, desc
});

it('lists and revokes other sessions but not the current one', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    DB::table('sessions')->insert([
        'id' => 'other-session-id', 'user_id' => $user->id, 'ip_address' => '10.0.0.9',
        'user_agent' => 'Other/1.0', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet/sessions')
        ->assertOk()
        ->assertJsonFragment(['id' => 'other-session-id', 'is_current' => false]);

    $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/wallet/sessions/other-session-id')
        ->assertNoContent();

    expect(DB::table('sessions')->where('id', 'other-session-id')->exists())->toBeFalse();
});
