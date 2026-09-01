<?php

use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

it('verifies email via the signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->getJson($url)->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('wrong@example.test'),
    ]);

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
})->todo('unblocked in Task 11 (POST /api/v1/deposits route)');
