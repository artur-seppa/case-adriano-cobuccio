<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Str;

it('stamps X-Request-Id on a 200, a 422 and a 401', function () {
    $this->getJson('/api/v1/wallet')->assertStatus(401)->assertHeader('X-Request-Id');

    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/wallet')->assertOk()->assertHeader('X-Request-Id');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertHeader('X-Request-Id');
});

it('echoes a client-supplied request id through an error response', function () {
    $this->withHeaders(['X-Request-Id' => 'client-req-12345'])
        ->getJson('/api/v1/wallet')
        ->assertStatus(401)
        ->assertHeader('X-Request-Id', 'client-req-12345')
        ->assertJsonPath('request_id', 'client-req-12345');
});
