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
