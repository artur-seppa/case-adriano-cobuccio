<?php

it('serves an OpenAPI 3.1 document covering the domain and auth endpoints', function () {
    $res = $this->getJson('/docs/api.json')->assertOk();

    expect($res->json('openapi'))->toStartWith('3.1')
        ->and($res->json('servers.0.url'))->toEndWith('/api')
        ->and(array_keys($res->json('paths')))->toContain(
            '/v1/deposits',
            '/v1/transfers',
            '/v1/wallet',
            '/v1/transactions/{transaction}/reversal',
            '/login',
            '/register',
        );
});

it('serves the docs UI page', function () {
    $this->get('/docs/api')->assertOk();
});

it('carries concrete request examples and the Idempotency-Key header', function () {
    $doc = $this->getJson('/docs/api.json')->assertOk()->json();

    $deposit = $doc['components']['schemas']['StoreDepositRequest']['properties'];
    expect($deposit['amount']['examples'])->toBe(['150.00'])
        ->and($deposit['currency']['examples'])->toBe(['BRL']);

    expect($doc['components']['schemas']['StoreTransferRequest']['properties']['recipient']['examples'])
        ->toBe(['bruno@wallet.test']);

    $header = collect($doc['paths']['/v1/deposits']['post']['parameters'])
        ->firstWhere('name', 'Idempotency-Key');
    expect($header)->not->toBeNull()
        ->and($header['required'])->toBeTrue()
        ->and($header['example'])->not->toBeEmpty();
});

it('documents the Fortify auth request bodies with examples', function () {
    $doc = $this->getJson('/docs/api.json')->assertOk()->json();

    $register = $doc['paths']['/register']['post']['requestBody']['content']['application/json']['schema'];
    expect($register['required'])->toContain('name', 'email', 'password', 'password_confirmation')
        ->and($register['properties']['email']['examples'])->toBe(['alice@wallet.test'])
        ->and($register['properties']['password']['examples'])->toBe(['Password1234']);

    $login = $doc['paths']['/login']['post']['requestBody']['content']['application/json']['schema'];
    if (isset($login['$ref'])) {
        $login = $doc['components']['schemas'][basename($login['$ref'])];
    }
    expect($login['properties']['email']['examples'])->toBe(['alice@wallet.test']);

    // logged-in self-service (PUT) — Fortify validates inside the action, so
    // these bodies are hand-declared too.
    $pwd = $doc['paths']['/user/password']['put']['requestBody']['content']['application/json']['schema'];
    expect($pwd['required'])->toBe(['current_password', 'password', 'password_confirmation']);
});

it('groups endpoints into ordered sections', function () {
    $doc = $this->getJson('/docs/api.json')->assertOk()->json();

    $tagNames = array_column($doc['tags'], 'name');
    expect($tagNames)->toBe([
        'Autenticação', 'Carteira', 'Transações', 'Depósitos',
        'Transferências', 'Estornos', 'Sessões', 'Tempo real',
    ]);
    expect($doc['tags'][0]['description'])->not->toBeEmpty();

    expect($doc['paths']['/v1/deposits']['post']['tags'])->toBe(['Depósitos'])
        ->and($doc['paths']['/v1/transfers']['post']['tags'])->toBe(['Transferências'])
        ->and($doc['paths']['/v1/transactions/{transaction}/reversal']['post']['tags'])->toBe(['Estornos'])
        ->and($doc['paths']['/v1/wallet/sessions']['get']['tags'])->toBe(['Sessões'])
        ->and($doc['paths']['/login']['post']['tags'])->toBe(['Autenticação']);
});
