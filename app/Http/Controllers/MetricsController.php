<?php

namespace App\Http\Controllers;

use App\Support\Metrics;
use Illuminate\Support\Facades\Cache;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke()
    {
        Metrics::gauge('sse_active_connections', 'Open SSE connections, summed across users.', (float) $this->countSseConnections());

        try {
            $renderer = new RenderTextFormat;
            $metrics = $renderer->render(Metrics::registry()->getMetricFamilySamples());
        } catch (\Throwable) {
            // Unlike counter()/gauge()/histogram() (silent, they never fail the
            // request they instrument), rendering *is* this endpoint's whole
            // job — surface the outage as 503 rather than a raw 500.
            return response('', 503, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
        }

        return response($metrics, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }

    private function countSseConnections(): int
    {
        try {
            // The slot counter is a cache entry (`Cache::add`), so it lives on
            // the cache store's own connection (`REDIS_CACHE_DB`), not the
            // `default` one — and the raw Redis key carries the store's prefix.
            $connection = Cache::getStore()->connection();
            $prefix = Cache::getStore()->getPrefix();

            $total = 0;
            $cursor = 0;

            do {
                $result = $connection->scan($cursor, ['match' => "{$prefix}sse:conns:*", 'count' => 100]);

                if ($result === false) {
                    break;
                }

                [$cursor, $keys] = $result;

                foreach ($keys as $key) {
                    $total += (int) Cache::get(substr($key, strlen($prefix)));
                }
            } while ((int) $cursor !== 0);

            return $total;
        } catch (\Throwable) {
            return 0;
        }
    }
}
