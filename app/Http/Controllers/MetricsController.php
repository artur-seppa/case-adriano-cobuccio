<?php

namespace App\Http\Controllers;

use App\Support\Metrics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke()
    {
        Metrics::gauge('sse_active_connections', 'Open SSE connections, summed across users.', (float) $this->countSseConnections());

        $renderer = new RenderTextFormat;
        $metrics = $renderer->render(Metrics::registry()->getMetricFamilySamples());

        return response($metrics, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }

    private function countSseConnections(): int
    {
        try {
            // The slot counter is a cache entry (`Cache::add`), so the raw
            // Redis key carries the cache store's prefix.
            $prefix = Cache::getStore()->getPrefix();
            $keys = Redis::connection()->keys("{$prefix}sse:conns:*");

            if ($keys === []) {
                return 0;
            }

            return (int) array_sum(array_map(
                fn (string $key) => (int) Cache::get(substr($key, strlen($prefix))),
                $keys,
            ));
        } catch (\Throwable) {
            return 0;
        }
    }
}
