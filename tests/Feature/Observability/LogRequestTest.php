<?php

use Illuminate\Support\Facades\Log;

it('logs one structured line per request with no sensitive payload', function () {
    $lines = [];
    Log::listen(function ($e) use (&$lines) {
        if ($e->message === 'request.completed') {
            $lines[] = $e->context;
        }
    });

    $this->getJson('/up')->assertOk();

    expect($lines)->toHaveCount(1);
    $ctx = $lines[0];
    expect($ctx)->toHaveKeys(['method', 'path', 'status', 'duration_ms', 'request_id', 'ip'])
        ->and($ctx['method'])->toBe('GET')
        ->and($ctx['path'])->toBe('up')
        ->and($ctx['status'])->toBe(200)
        ->and($ctx['duration_ms'])->toBeGreaterThanOrEqual(0);
    expect($ctx)->not->toHaveKey('body');
    expect($ctx)->not->toHaveKey('headers');
    expect($ctx)->not->toHaveKey('query');
});
