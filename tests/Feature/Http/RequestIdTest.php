<?php

it('echoes a caller-supplied request id', function () {
    $this->get('/up', ['X-Request-Id' => 'abc123def456'])
        ->assertHeader('X-Request-Id', 'abc123def456');
});

it('generates a request id when none is supplied', function () {
    $res = $this->get('/up');
    $id = $res->headers->get('X-Request-Id');
    expect($id)->not->toBeNull()->and(strlen($id))->toBeGreaterThanOrEqual(8);
});

it('rejects a malformed caller id and generates its own', function () {
    $res = $this->get('/up', ['X-Request-Id' => 'has spaces & bad']);
    expect($res->headers->get('X-Request-Id'))->not->toBe('has spaces & bad');
});
