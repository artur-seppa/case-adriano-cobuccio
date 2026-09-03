<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\ErrorLogHandler;

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
    // ErrorLogHandler (not StreamHandler on php://stderr, which was found to
    // break every request under FrankenPHP's Octane worker mode) + JSON.
    expect(config('logging.channels.stderr.handler'))->toBe(ErrorLogHandler::class);
    expect(config('logging.channels.stderr.handler_with.messageType'))->toBe(0);
    expect(config('logging.channels.stderr.formatter'))->toBe(JsonFormatter::class);
});

it('extracts trace_id from a W3C traceparent header', function () {
    $traceId = str_repeat('a', 32);
    $traceparent = "00-{$traceId}-".str_repeat('b', 16).'-01';

    $this->get('/up', ['traceparent' => $traceparent]);

    expect(Context::get('trace_id'))->toBe($traceId);
});

it('falls back to request_id as trace_id when no traceparent header is sent', function () {
    $this->get('/up');

    expect(Context::get('trace_id'))->toBe(Context::get('request_id'));
});
