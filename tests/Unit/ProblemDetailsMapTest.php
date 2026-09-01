<?php

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\TransactionCouldNotCompleteException;
use App\Domain\Wallet\Support\ProblemDetails;
use App\Domain\Wallet\Support\ProblemMapper;
use App\Domain\Wallet\ValueObjects\Money;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(TestCase::class);

it('builds a problem body with the stable shape', function () {
    app()->instance('request_id', 'req-123');
    $res = ProblemDetails::response(422, 'insufficient-funds', 'Insufficient funds', 'Saldo insuficiente.', [
        'available' => '10.00', 'requested' => '30.00',
    ]);

    expect($res->getStatusCode())->toBe(422)
        ->and($res->headers->get('Content-Type'))->toContain('application/problem+json');

    $body = $res->getData(true);
    expect($body)->toMatchArray([
        'type' => 'https://wallet.test/problems/insufficient-funds',
        'title' => 'Insufficient funds',
        'status' => 422,
        'detail' => 'Saldo insuficiente.',
        'request_id' => 'req-123',
        'available' => '10.00',
        'requested' => '30.00',
    ]);
});

it('omits detail and request_id when they are null', function () {
    app()->forgetInstance('request_id');
    $res = ProblemDetails::response(403, 'forbidden', 'This action is unauthorized.');

    $body = $res->getData(true);
    expect($body)->toEqual([
        'type' => 'https://wallet.test/problems/forbidden',
        'title' => 'This action is unauthorized.',
        'status' => 403,
    ]);
});

it('maps a domain exception to its status via context()', function () {
    $e = new InsufficientFundsException(
        available: Money::fromCents(1000),
        requested: Money::fromCents(3000),
    );
    expect($e->context())->toHaveKeys(['available', 'requested', 'currency']); // Plano 1: context() já retorna decimalString + currency
});

it('maps InsufficientFundsException to 422 with the context as extras', function () {
    $res = ProblemMapper::map(new InsufficientFundsException(
        available: Money::fromCents(1000),
        requested: Money::fromCents(3000),
    ));

    expect($res->getStatusCode())->toBe(422);
    $body = $res->getData(true);
    expect($body)->toMatchArray([
        'type' => 'https://wallet.test/problems/insufficient-funds',
        'status' => 422,
        'available' => '10.00',
        'requested' => '30.00',
        'currency' => 'BRL',
    ]);
});

it('maps framework exceptions to their spec status', function () {
    expect(ProblemMapper::map(new AuthenticationException)->getStatusCode())->toBe(401)
        ->and(ProblemMapper::map(new NotFoundHttpException)->getStatusCode())->toBe(404)
        ->and(ProblemMapper::map(ValidationException::withMessages(['x' => 'bad']))->getStatusCode())->toBe(422)
        ->and(ProblemMapper::map(new RuntimeException('boom'))->getStatusCode())->toBe(500);
});

it('maps ValidationException with an errors bag', function () {
    $res = ProblemMapper::map(ValidationException::withMessages(['amount' => 'required']));

    expect($res->getData(true))->toMatchArray([
        'type' => 'https://wallet.test/problems/validation-failed',
        'errors' => ['amount' => ['required']],
    ]);
});

it('attaches Retry-After when throttled (header chaining works)', function () {
    $res = ProblemMapper::map(new ThrottleRequestsException('Too many', null, ['Retry-After' => 30]));

    expect($res->getStatusCode())->toBe(429)
        ->and($res->headers->get('Retry-After'))->toBe('30')
        ->and($res->getData(true)['type'])->toBe('https://wallet.test/problems/rate-limited');
});

it('maps TransactionCouldNotCompleteException to 503 with Retry-After', function () {
    $res = ProblemMapper::map(new TransactionCouldNotCompleteException(3));

    expect($res->getStatusCode())->toBe(503)
        ->and($res->headers->get('Retry-After'))->toBe('1')
        ->and($res->getData(true)['type'])->toBe('https://wallet.test/problems/transaction-could-not-complete');
});
