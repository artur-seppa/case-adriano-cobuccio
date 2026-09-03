<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

it('points the verification link at the SPA origin with a host-independent signature', function () {
    Notification::fake();
    config()->set('app.frontend_url', 'http://localhost:3000');

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, QueuedVerifyEmail::class, function (QueuedVerifyEmail $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        expect($url)->toStartWith('http://localhost:3000/api/email/verify/'.$user->id.'/')
            ->and($url)->toContain('signature=')
            ->and($url)->toContain('expires=');

        return true;
    });
});

it('verifies email via the signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ], absolute: false);

    $this->actingAs($user)->getJson($url)
        ->assertOk()
        ->assertJsonPath('verified', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('returns a browser-friendly confirmation page when the link is opened directly', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ], absolute: false);

    $this->actingAs($user)->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('E-mail verificado');
});

it('rejects a tampered verification hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('wrong@example.test'),
    ], absolute: false);

    $this->actingAs($user)->getJson($url)->assertStatus(403);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('blocks money endpoints until the email is verified', function () {
    $user = User::factory()->unverified()->create();
    Wallet::factory()->forUser($user)->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/deposits', ['amount' => '10.00', 'currency' => 'BRL'], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])
        ->assertStatus(403)
        ->assertJsonPath('type', 'https://wallet.test/problems/forbidden');
});
