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

it('actually builds and sends the reset notification without a 500', function () {
    // No Notification::fake() here on purpose: faking short-circuits the URL
    // build, which is exactly where the `route(password.reset)` 500 lived.
    $user = User::factory()->create();

    $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
});

it('points the reset link at the SPA frontend, not a backend view route', function () {
    config(['app.frontend_url' => 'https://app.example']);
    $user = User::factory()->create(['email' => 'x@wallet.test']);

    $mail = (new ResetPassword('tok-123'))->toMail($user);

    expect($mail->actionUrl)
        ->toBe('https://app.example/reset-password?token=tok-123&email=x%40wallet.test');
});
