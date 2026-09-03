<?php

it('honours X-Forwarded-Proto from the proxy without breaking the boot', function () {
    $this->get('/up', [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'wallet.example',
    ])->assertOk();
});

it('trusts a private-range proxy for forwarded headers', function () {
    // Simula um hop atrás do proxy da rede Docker.
    $response = $this->withServerVariables(['REMOTE_ADDR' => '172.20.0.5'])
        ->get('/up', ['X-Forwarded-Proto' => 'https']);

    $response->assertOk();
    expect(request()->getScheme())->toBe('https');
});
