<?php

namespace App\Domain\Wallet\Support;

use Illuminate\Http\JsonResponse;

final class ProblemDetails
{
    public const TYPE_BASE = 'https://wallet.test/problems/';

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function response(int $status, string $slug, string $title, ?string $detail = null, array $extra = []): JsonResponse
    {
        $body = array_filter([
            'type' => self::TYPE_BASE.$slug,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'request_id' => app()->bound('request_id') ? app('request_id') : null,
        ], fn ($v) => $v !== null);

        return new JsonResponse($body + $extra, $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
