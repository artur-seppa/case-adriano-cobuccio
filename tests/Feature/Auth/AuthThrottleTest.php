<?php

use App\Models\User;

it('throttles login after 5 failed attempts per minute', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope']);
    }

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/rate-limited');
});
