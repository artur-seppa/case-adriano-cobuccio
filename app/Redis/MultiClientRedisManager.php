<?php

namespace App\Redis;

use Illuminate\Redis\RedisManager;

/**
 * Laravel's stock RedisManager picks the client library (phpredis / predis)
 * once, from the top-level `database.redis.client`, and uses it for every
 * connection. We need both at the same time: phpredis for queue, cache and
 * Horizon (Horizon does not support predis), and predis for the SSE `pubsub`
 * connection — phpredis' `Redis` object has no `pubSubLoop()`, which
 * StreamController relies on.
 *
 * This subclass honours an optional per-connection `client` key
 * (see config/database.php). When present and different from the default
 * driver, it swaps the driver for just that resolve() call.
 */
class MultiClientRedisManager extends RedisManager
{
    public function resolve($name = null)
    {
        $name = $name ?: 'default';

        $client = $this->config[$name]['client'] ?? null;

        if ($client === null || $client === $this->driver) {
            return parent::resolve($name);
        }

        $default = $this->driver;
        $this->driver = $client;

        try {
            return parent::resolve($name);
        } finally {
            $this->driver = $default;
        }
    }
}
