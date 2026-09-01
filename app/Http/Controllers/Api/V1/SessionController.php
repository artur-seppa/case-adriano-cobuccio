<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Support\ProblemDetails;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads/deletes rows from the framework `sessions` table directly — there is no
 * Eloquent model for it, and the arch rule for controllers whitelists this class.
 */
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentId = $this->currentSessionId($request);

        $rows = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => $currentId !== null && hash_equals($session->id, $currentId),
            ])->all(),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse|Response
    {
        $currentId = $this->currentSessionId($request);

        if ($currentId !== null && hash_equals($currentId, $id)) {
            return ProblemDetails::response(
                422,
                'cannot-revoke-current-session',
                'Use logout to end the current session.',
            );
        }

        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return response()->noContent();
    }

    private function currentSessionId(Request $request): ?string
    {
        return $request->hasSession() ? $request->session()->getId() : null;
    }
}
