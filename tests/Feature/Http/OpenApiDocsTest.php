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
