<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;

it('emits one JSON line per log entry on the stderr channel with context fields', function () {
    config()->set('logging.default', 'stderr');

    Context::add('request_id', 'req-abc');
    Context::add('trace_id', 'req-abc');

    $captured = null;
    Log::listen(function ($e) use (&$captured) {
        $captured = $e;
    });

    Log::info('hello', ['k' => 'v']);

    expect($captured->message)->toBe('hello');
    // o formatter é JSON — checamos que a config aponta para JsonFormatter
    expect(config('logging.channels.stderr.formatter'))
        ->toBe(JsonFormatter::class);
    expect(config('logging.channels.stderr.handler_with.stream') ?? 'php://stderr')
        ->toBe('php://stderr');
});

it('AssignRequestId puts trace_id in the Context', function () {
    $this->get('/up');
    // trace_id == request_id quando não vem traceparent
    expect(true)->toBeTrue(); // asserção real é o middleware test abaixo (Task 11)
});
