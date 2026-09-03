<?php

namespace App\Http\Middleware;

use App\Domain\Wallet\Support\CanonicalJson;
use App\Domain\Wallet\Support\ProblemDetails;
use App\Support\Metrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency Layer A (spec §8): a client-supplied UUID `Idempotency-Key` is
 * recorded before the write runs; a repeat of the same key + body replays the
 * stored response instead of doing the work twice. Backed by the
 * `idempotency_keys` table in a short transaction of its own, separate from the
 * money transaction the controller opens.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if (! Str::isUuid($key)) {
            return ProblemDetails::response(
                400,
                'idempotency-key-required',
                'A valid UUID Idempotency-Key header is required for this operation.',
            );
        }

        $fingerprint = CanonicalJson::fingerprint($request->all());
        $now = now();

        $inserted = DB::table('idempotency_keys')->insertOrIgnore([
            'key' => $key,
            'user_id' => $request->user()->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'request_fingerprint' => $fingerprint,
            'status' => 'locked',
            'locked_at' => $now,
            'expires_at' => $now->copy()->addDay(),
        ]);

        if ($inserted === 0) {
            return $this->replayOrConflict($key, $fingerprint, $request->user()->id, $request->path());
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            DB::table('idempotency_keys')->where('key', $key)->where('status', 'locked')->delete();
            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            DB::table('idempotency_keys')->where('key', $key)->where('status', 'locked')->delete();

            return $response;
        }

        $body = $response->getContent();

        DB::table('idempotency_keys')->where('key', $key)->update([
            'status' => 'completed',
            'response_status' => $response->getStatusCode(),
            'response_body' => $body === false ? null : $body,
            'transaction_id' => data_get(json_decode((string) $body, true), 'data.id'),
            'completed_at' => now(),
        ]);

        $response->headers->set('Idempotency-Replayed', 'false');

        return $response;
    }

    private function replayOrConflict(string $key, string $fingerprint, string $userId, string $path): Response
    {
        $row = DB::table('idempotency_keys')->where('key', $key)->first();

        // The key exists but belongs to another user, or was used on a different
        // route: a client-chosen UUID collision. Never replay someone else's
        // response body (it carries their transaction, amounts, counterparty).
        if ($row->user_id !== $userId || $row->path !== $path) {
            return ProblemDetails::response(
                422,
                'idempotency-key-reused',
                'This Idempotency-Key was already used for a different request.',
            );
        }

        if ($row->status === 'locked') {
            return ProblemDetails::response(
                409,
                'idempotency-conflict',
                'A request with this Idempotency-Key is already in progress.',
            )->withHeaders(['Retry-After' => '1']);
        }

        if (! hash_equals($row->request_fingerprint, $fingerprint)) {
            return ProblemDetails::response(
                422,
                'idempotency-key-reused',
                'This Idempotency-Key was already used with different request parameters.',
            );
        }

        Metrics::counter('idempotency_replays_total', 'Requests served from a replayed idempotent response.', [], []);

        return response($row->response_body, $row->response_status)
            ->header('Content-Type', 'application/json')
            ->header('Idempotency-Replayed', 'true');
    }
}
