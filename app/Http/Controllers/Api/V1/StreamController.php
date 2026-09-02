<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\ConnectionException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-Sent Events (spec §11). Subscribes to the authenticated user's Redis
 * channel — the user id comes from the session, never a query param — and
 * forwards each published nudge as an SSE frame, with a keep-alive heartbeat.
 * At most 3 concurrent streams per user. The long-lived connection is cheap
 * under FrankenPHP worker mode.
 */
class StreamController extends Controller
{
    private const MAX_STREAMS_PER_USER = 3;

    private const HEARTBEAT_SECONDS = 20;

    /** A stale slot self-expires even if a stream dies without running its finally. */
    private const SLOT_TTL_SECONDS = 900;

    public function __invoke(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;
        $slot = "sse:conns:{$userId}";

        Cache::add($slot, 0, self::SLOT_TTL_SECONDS);

        if (Cache::increment($slot) > self::MAX_STREAMS_PER_USER) {
            Cache::decrement($slot);
            abort(429, 'Too many concurrent streams for this user.');
        }

        // Exactly-once release: the generator's finally runs on a normal close;
        // the shutdown function is the fallback for a fatal/timeout that skips it.
        $released = false;
        $release = function () use ($slot, &$released) {
            if ($released) {
                return;
            }
            $released = true;
            Cache::decrement($slot);
        };
        register_shutdown_function($release);

        return response()->eventStream(function () use ($userId, $release) {
            try {
                yield from $this->listen($userId);
            } finally {
                $release();
            }
        });
    }

    /**
     * @return \Generator<int, StreamedEvent>
     */
    private function listen(string $userId): \Generator
    {
        $pubsub = Redis::connection('pubsub')->client()->pubSubLoop();
        $pubsub->subscribe("user-events:{$userId}");
        $lastBeat = time();

        try {
            while (true) {
                try {
                    $message = $pubsub->current();
                } catch (ConnectionException) {
                    $message = null; // read timeout — fall through to heartbeat/abort check
                }

                if ($message !== null && $message->kind === 'message') {
                    $payload = json_decode($message->payload, true);
                    yield new StreamedEvent(
                        event: is_array($payload) ? ($payload['type'] ?? 'update') : 'update',
                        data: $message->payload,
                    );
                }

                if (connection_aborted()) {
                    break;
                }

                if (time() - $lastBeat >= self::HEARTBEAT_SECONDS) {
                    $lastBeat = time();
                    yield new StreamedEvent(event: 'ping', data: '{}');
                }
            }
        } finally {
            $pubsub->stop(true);
        }
    }
}
