<?php

use App\Redis\MultiClientRedisManager;
use Illuminate\Contracts\Redis\Connector;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Redis\Connections\PredisConnection;
use Predis\Client as PredisClient;
use Tests\TestCase;

uses(TestCase::class);

/**
 * MultiClientRedisManager::resolve() has exactly two branches:
 *
 *  1. no-op fast path — the named connection's `client` matches (or is absent),
 *     so it just delegates to the stock RedisManager::resolve().
 *  2. swap path — the named connection's `client` differs from the manager's
 *     top-level driver, so `$this->driver` is temporarily swapped for the
 *     duration of the resolve() call, then restored in a `finally`.
 *
 * In this app's own runtime, branch 2 fires for exactly one connection
 * (`pubsub`, pinned to predis so the SSE stream can use pubSubLoop() while
 * everything else — Horizon, cache, queue — stays on phpredis). But in the
 * automated test environment REDIS_CLIENT=predis (phpunit.xml) and the
 * `pubsub` connection is also hardcoded to `'client' => 'predis'`
 * (config/database.php), so `$client === $this->driver` is always true there
 * and branch 2 never runs under the app's real config.
 *
 * This test builds its own manager + config, independent of config/database.php
 * and REDIS_CLIENT, to force branch 2 and assert what it actually does:
 *   - the overridden connection is built with the *different* client, and
 *   - `$this->driver` is restored afterwards, proven by resolving a second,
 *     non-overridden connection and getting the *default* client back.
 *
 * The default-driver side uses a fake `Connector` registered via ->extend()
 * (the same mechanism Laravel's own Redis::extend() uses) rather than the
 * real `phpredis` driver name, because this host has no ext-redis installed
 * (REDIS_CLIENT=predis is used precisely because of that) — phpredis's
 * connector eagerly opens a real socket in connect(), which a unit test must
 * not depend on. Predis\Client itself is safe to construct with no server:
 * it connects lazily on the first command, so the swapped-to-predis side of
 * this test needs no live Redis either.
 */
class FakeDefaultRedisConnection extends Connection
{
    public function createSubscription($channels, Closure $callback, $method = 'subscribe')
    {
        throw new RuntimeException('not exercised by this test');
    }
}

class FakeDefaultRedisConnector implements Connector
{
    public function connect(array $config, array $options)
    {
        return new FakeDefaultRedisConnection;
    }

    public function connectToCluster(array $config, array $clusterOptions, array $options)
    {
        throw new RuntimeException('not exercised by this test');
    }
}

function makeMultiClientRedisManager(): MultiClientRedisManager
{
    $config = [
        'client' => 'fake-default',
        'options' => [],
        'default' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
        ],
        // Mirrors config/database.php's `pubsub` connection: a `client` key
        // that differs from the manager's top-level driver.
        'pubsub' => [
            'client' => 'predis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
        ],
    ];

    $manager = new MultiClientRedisManager(app(), 'fake-default', $config);
    $manager->extend('fake-default', fn () => new FakeDefaultRedisConnector);

    return $manager;
}

it('swaps to the connection-specific client for the one connection that overrides it', function () {
    $manager = makeMultiClientRedisManager();

    $pubsub = $manager->resolve('pubsub');

    expect($pubsub)->toBeInstanceOf(PredisConnection::class)
        ->and($pubsub->client())->toBeInstanceOf(PredisClient::class);
});

it('restores the default driver after resolving an overridden connection', function () {
    $manager = makeMultiClientRedisManager();

    // Trigger the swap branch first.
    $manager->resolve('pubsub');

    // $driver is protected on the parent RedisManager; assert the restore
    // directly as well as through behaviour, so a regression that leaves the
    // swap in place is caught even if it happens to also produce the right
    // connection type below.
    $driver = (new ReflectionClass($manager))->getProperty('driver');
    $driver->setAccessible(true);
    expect($driver->getValue($manager))->toBe('fake-default');

    // Resolving a second, non-overridden connection must still use the
    // manager's default client, not the one 'pubsub' swapped in.
    $default = $manager->resolve('default');

    expect($default)->toBeInstanceOf(FakeDefaultRedisConnection::class);
});

it('takes the no-op fast path when a connection has no client override', function () {
    $manager = makeMultiClientRedisManager();

    $default = $manager->resolve('default');

    expect($default)->toBeInstanceOf(FakeDefaultRedisConnection::class);
});
