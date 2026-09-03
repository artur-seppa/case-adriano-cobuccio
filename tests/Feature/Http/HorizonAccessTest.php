<?php

use App\Models\User;

it('serves the Horizon dashboard to an authenticated user in non-production', function () {
    // APP_ENV=testing → não é production → gate abre
    $this->actingAs(User::factory()->create())
        ->get('/horizon')
        ->assertOk();
});

it('blocks the Horizon dashboard when the env is production and the email is not allow-listed', function () {
    config()->set('app.env', 'production');
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['email' => 'nobody@wallet.test']))
        ->get('/horizon')
        ->assertForbidden();
});
