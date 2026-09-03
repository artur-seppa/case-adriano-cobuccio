<?php

it('renders Prometheus text with the wallet metric families', function () {
    // dispara um request para popular http_server_request_duration_seconds
    $this->getJson('/up');

    $body = $this->get('/metrics')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8')
        ->getContent();

    expect($body)
        ->toContain('wallet_http_server_request_duration_seconds')
        ->toContain('# TYPE');
});
