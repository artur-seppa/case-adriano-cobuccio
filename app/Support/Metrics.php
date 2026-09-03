<?php

namespace App\Support;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis as RedisAdapter;

class Metrics
{
    private static ?CollectorRegistry $registry = null;

    public static function registry(): CollectorRegistry
    {
        if (self::$registry === null) {
            $adapter = app()->environment('testing')
                ? new InMemory
                : new RedisAdapter([
                    'host' => config('database.redis.default.host'),
                    'port' => (int) config('database.redis.default.port'),
                    'password' => config('database.redis.default.password') ?: null,
                    'database' => (int) config('database.redis.default.database', 0),
                ]);

            self::$registry = new CollectorRegistry($adapter, false);
        }

        return self::$registry;
    }

    public static function counter(string $name, string $help, array $labels, array $labelValues): void
    {
        try {
            self::registry()->getOrRegisterCounter('wallet', $name, $help, $labels)->inc($labelValues);
        } catch (\Throwable) {
            // A metrics outage must never fail the request it's instrumenting.
        }
    }

    public static function gauge(string $name, string $help, float $value, array $labels = [], array $labelValues = []): void
    {
        try {
            self::registry()->getOrRegisterGauge('wallet', $name, $help, $labels)->set($value, $labelValues);
        } catch (\Throwable) {
            //
        }
    }

    public static function histogram(string $name, string $help, float $value, array $labels, array $labelValues, array $buckets): void
    {
        try {
            self::registry()->getOrRegisterHistogram('wallet', $name, $help, $labels, $buckets)->observe($value, $labelValues);
        } catch (\Throwable) {
            //
        }
    }
}
