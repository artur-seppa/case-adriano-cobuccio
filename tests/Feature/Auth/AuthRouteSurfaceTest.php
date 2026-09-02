<?php

use Illuminate\Support\Facades\Route;

it('exposes exactly the intended Fortify auth endpoints', function () {
    $fortify = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->methods()[0].' '.$r->uri())
        ->filter(fn ($r) => str_contains($r, 'api/login')
            || str_contains($r, 'api/logout')
            || str_contains($r, 'api/register')
            || str_contains($r, 'api/forgot-password')
            || str_contains($r, 'api/reset-password')
            || str_contains($r, 'api/email/verif')
            || str_contains($r, 'api/user/'))
        ->sort()->values()->all();

    expect($fortify)->toBe([
        'GET api/email/verify/{id}/{hash}',
        'POST api/email/verification-notification',
        'POST api/forgot-password',
        'POST api/login',
        'POST api/logout',
        'POST api/register',
        'POST api/reset-password',
        'PUT api/user/password',
        'PUT api/user/profile-information',
    ]);
});

it('does not register the unused password-confirmation, 2FA or passkey routes', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->all();

    expect($names)->not->toContain('password.confirm.store')
        ->and($names)->not->toContain('password.confirmation')
        ->and(collect($names)->filter(fn ($n) => str_contains($n, 'two-factor'))->all())->toBe([])
        ->and(collect($names)->filter(fn ($n) => str_contains($n, 'passkey'))->all())->toBe([]);
});
