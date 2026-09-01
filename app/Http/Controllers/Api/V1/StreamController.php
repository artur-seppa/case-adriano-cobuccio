<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events (spec §11). Subscribes to the authenticated user's Redis
 * channel — the user id comes from the session, never a query param — and
 * forwards each published nudge as an SSE frame, with a keep-alive heartbeat.
 * At most 3 concurrent streams per user. The long-lived connection is cheap
 * under FrankenPHP worker mode (Plan 4).
 */
class StreamController extends Controller
{
    private const MAX_STREAMS_PER_USER = 3;

    private const HEARTBEAT_SECONDS = 20;

    public function __invoke(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $slot = "sse:conns:{$userId}";

        if (Cache::increment($slot) > self::MAX_STREAMS_PER_USER) {
            Cache::decrement($slot);
            abort(429, 'Too many concurrent streams for this user.');
        }

        return response()->eventStream(function () use ($userId, $slot) {
            try {
                yield from $this->listen($userId);
            } finally {
                Cache::decrement($slot);
            }
        });
    }

    /**
     * @return \Generator<int, StreamedEvent>
     */
    private function listen(string $userId): \Generator
    {
        $pubsub = Redis::connection()->client()->pubSubLoop();
        $pubsub->subscribe("user-events:{$userId}");
        $lastBeat = time();

        try {
            foreach ($pubsub as $message) {
                if ($message->kind === 'message') {
                    $payload = json_decode($message->payload, true);
                    yield new StreamedEvent(
                        event: is_array($payload) ? ($payload['type'] ?? 'update') : 'update',
                        data: $message->payload,
                    );
                }

                if (time() - $lastBeat >= self::HEARTBEAT_SECONDS) {
                    $lastBeat = time();
                    yield new StreamedEvent(event: 'ping', data: '{}');
                }

                if (connection_aborted()) {
                    break;
                }
            }
        } finally {
            $pubsub->unsubscribe();
        }
    }
}
