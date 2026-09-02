<?php

test('the root path is a JSON index pointing at the docs and health check', function () {
    $this->getJson('/')
        ->assertOk()
        ->assertJsonStructure(['name', 'docs', 'openapi', 'health'])
        ->assertJsonPath('health', fn ($url) => str_ends_with($url, '/up'));
});
