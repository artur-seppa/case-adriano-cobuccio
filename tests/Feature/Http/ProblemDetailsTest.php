<?php

use App\Models\User;
use Illuminate\Support\Str;

it('returns problem+json for validation errors', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/deposits', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/validation-failed')
        ->assertJsonStructure(['type', 'title', 'status', 'errors', 'request_id']);
});

it('returns 401 problem+json when unauthenticated', function () {
    $this->getJson('/api/v1/wallet')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/unauthenticated');
});

it('returns 404 problem+json for a missing model', function () {
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/transactions/01JZZZNONEXISTENT0000000000')
        ->assertStatus(404)
        ->assertJsonPath('type', 'https://wallet.test/problems/not-found');
});
