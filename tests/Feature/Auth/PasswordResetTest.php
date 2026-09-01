<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('sends a reset link and resets the password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NewSecret123!',
        'password_confirmation' => 'NewSecret123!',
    ])->assertNoContent();

    $this->postJson('/api/login', ['email' => $user->email, 'password' => 'NewSecret123!'])
        ->assertNoContent();
});

it('rejects a forgot-password request for an unknown email (Fortify default)', function () {
    Notification::fake();

    $this->postJson('/api/forgot-password', ['email' => 'nobody@example.test'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json');

    Notification::assertNothingSent();
});
