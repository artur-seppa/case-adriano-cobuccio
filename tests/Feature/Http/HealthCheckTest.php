<?php

use Illuminate\Support\Facades\Redis;

it('returns 200 with ok components when DB and Redis are up', function () {
    $this->getJson('/health')
        ->assertOk()
        ->assertJson(['status' => 'ok', 'db' => 'ok', 'redis' => 'ok']);
});

it('returns 503 when Redis is unreachable', function () {
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('down'));

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('redis', 'down');
});
