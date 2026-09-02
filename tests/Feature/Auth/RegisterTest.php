<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('registers a user, provisions a zero-balance wallet and sends verification', function () {
    Notification::fake();

    $this->postJson('/api/register', [
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ])
        ->assertCreated()
        ->assertJsonPath('requires_email_verification', true)
        ->assertJsonPath('user.email', 'ana@example.test')
        ->assertJsonPath('user.name', 'Ana')
        ->assertHeader('X-Request-Id');

    $user = User::where('email', 'ana@example.test')->firstOrFail();
    expect(Wallet::where('user_id', $user->id)->value('balance_cents'))->toBe(0)
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a duplicate email with problem+json 422', function () {
    User::factory()->create(['email' => 'dup@example.test']);

    $this->postJson('/api/register', [
        'name' => 'X',
        'email' => 'dup@example.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://wallet.test/problems/validation-failed')
        ->assertJsonStructure(['type', 'title', 'status', 'errors', 'request_id']);
});

it('rejects a weak or unconfirmed password', function () {
    $this->postJson('/api/register', [
        'name' => 'Y',
        'email' => 'y@example.test',
        'password' => 'short',
        'password_confirmation' => 'nope',
    ])->assertStatus(422)->assertJsonPath('type', 'https://wallet.test/problems/validation-failed');

    expect(User::where('email', 'y@example.test')->exists())->toBeFalse();
});

it('provisions user and wallet atomically', function () {
    $before = User::count();

    $this->postJson('/api/register', [
        'name' => 'Ok',
        'email' => 'ok@example.test',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ])->assertCreated();

    $userId = User::where('email', 'ok@example.test')->value('id');
    expect(User::count())->toBe($before + 1)
        ->and(Wallet::where('user_id', $userId)->count())->toBe(1);
});
