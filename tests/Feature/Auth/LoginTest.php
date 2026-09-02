<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials and starts a session', function () {
    $user = User::factory()->create(['password' => Hash::make('Secret123!')]);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Secret123!'])
        ->assertNoContent();

    $this->assertAuthenticatedAs($user);
});

it('rejects bad credentials with 422 problem+json', function () {
    $user = User::factory()->create(['password' => Hash::make('Secret123!')]);

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/validation-failed');

    $this->assertGuest();
});

it('rejects an unknown email with 422', function () {
    $this->postJson('/api/login', ['email' => 'ghost@example.test', 'password' => 'whatever'])
        ->assertStatus(422);
});
