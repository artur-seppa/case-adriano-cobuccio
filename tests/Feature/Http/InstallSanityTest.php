<?php

it('keeps the health check open and unauthenticated', function () {
    $this->get('/up')->assertOk();
});

it('rejects an unauthenticated call to a would-be protected route', function () {
    $this->getJson('/api/v1/wallet')->assertStatus(401);
});

it('exposes the sanctum csrf cookie route', function () {
    $this->get('/sanctum/csrf-cookie')->assertNoContent();
});

it('registers fortify action routes under /api without views', function () {
    // Screen GET does not exist (views=false); POST exists and validates.
    $this->postJson('/api/login', [])->assertStatus(422);
});
