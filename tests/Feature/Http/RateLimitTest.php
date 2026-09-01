<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Str;

it('throttles money writes at 10/min per user', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->withBalanceCents(1_000_000)->create();

    foreach (range(1, 10) as $i) {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/deposits', ['amount' => '1.00', 'currency' => 'BRL'],
                ['Idempotency-Key' => (string) Str::uuid()])
            ->assertCreated();
    }

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', ['amount' => '1.00', 'currency' => 'BRL'],
            ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(429)
        ->assertJsonPath('type', 'https://wallet.test/problems/rate-limited')
        ->assertHeader('Retry-After');
});

it('throttles reads at 60/min per user', function () {
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')->assertOk();
    }

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')
        ->assertStatus(429)
        ->assertJsonPath('type', 'https://wallet.test/problems/rate-limited');
});
