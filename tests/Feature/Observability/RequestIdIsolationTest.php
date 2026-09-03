<?php

use App\Domain\Wallet\Support\ProblemDetails;
use App\Domain\Wallet\Support\ProblemMapper;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Sob Octane, `app()->instance('request_id', ...)` é um binding no container
 * de longa duração — pode vazar de um request para o outro se algo não
 * limpar. `Context` é per-request (o próprio Octane dá flush). O teste chama
 * ProblemDetails/ProblemMapper diretamente (em vez de bater numa rota via
 * HTTP) porque o middleware AssignRequestId roda no pipeline global e
 * re-popularia Context E o container com o mesmo valor fresco antes de
 * qualquer exceção ser renderizada — tornando indistinguível, num teste via
 * rota HTTP normal, se o corpo do erro leu de um ou de outro.
 */
it('ProblemDetails reads request_id from the Context, not a leaked container binding', function () {
    Context::add('request_id', 'ctx-123');
    app()->instance('request_id', 'stale-999');

    $response = ProblemDetails::response(404, 'not-found', 'Not Found');

    expect($response->getData(true)['request_id'])->toBe('ctx-123');
});

it('ProblemMapper logs request_id from the Context on a critical (5xx) error', function () {
    Context::add('request_id', 'ctx-456');
    app()->instance('request_id', 'stale-999');

    $captured = null;
    Log::listen(function ($e) use (&$captured) {
        if ($e->message === 'internal') {
            $captured = $e->context;
        }
    });

    ProblemMapper::map(new RuntimeException('boom'));

    expect($captured)->not->toBeNull()
        ->and($captured['request_id'])->toBe('ctx-456');
});
