<?php

namespace App\Domain\Wallet\Support;

use App\Domain\Wallet\Exceptions\CannotReverseReversalException;
use App\Domain\Wallet\Exceptions\CurrencyMismatchException;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\Exceptions\TransactionAlreadyReversedException;
use App\Domain\Wallet\Exceptions\TransactionCouldNotCompleteException;
use App\Domain\Wallet\Exceptions\UnbalancedLedgerException;
use App\Domain\Wallet\Exceptions\WalletDomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ProblemMapper
{
    public static function map(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => ProblemDetails::response(
                422, 'validation-failed', 'The given data was invalid.', null,
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => ProblemDetails::response(
                401, 'unauthenticated', 'Unauthenticated.',
            ),
            $e instanceof AuthorizationException => ProblemDetails::response(
                403, 'forbidden', 'This action is unauthorized.',
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ProblemDetails::response(
                404, 'not-found', 'Resource not found.',
            ),
            $e instanceof ThrottleRequestsException => ProblemDetails::response(
                429, 'rate-limited', 'Too many requests.', null,
                array_filter(['retry_after' => $e->getHeaders()['Retry-After'] ?? null]),
            )->withHeaders($e->getHeaders()),
            $e instanceof InsufficientFundsException => ProblemDetails::response(
                422, 'insufficient-funds', 'Insufficient funds.', $e->getMessage(), $e->context(),
            ),
            $e instanceof CurrencyMismatchException => ProblemDetails::response(
                422, 'currency-mismatch', 'Currency mismatch.', $e->getMessage(), $e->context(),
            ),
            $e instanceof TransactionAlreadyReversedException => ProblemDetails::response(
                409, 'transaction-already-reversed', 'Transaction already reversed.', $e->getMessage(), $e->context(),
            ),
            $e instanceof CannotReverseReversalException => ProblemDetails::response(
                422, 'cannot-reverse-reversal', 'A reversal cannot be reversed.', $e->getMessage(),
            ),
            $e instanceof TransactionCouldNotCompleteException => ProblemDetails::response(
                503, 'transaction-could-not-complete', 'Could not complete the transaction, retry.', null,
                [],
            )->withHeaders(['Retry-After' => '1']),
            $e instanceof UnbalancedLedgerException => self::critical($e, 'ledger-imbalance'),
            $e instanceof WalletDomainException => ProblemDetails::response(
                422, 'domain-error', class_basename($e), $e->getMessage(), method_exists($e, 'context') ? $e->context() : [],
            ),
            $e instanceof HttpExceptionInterface => self::httpException($e),
            default => self::critical($e, 'internal'),
        };
    }

    /**
     * Any `abort($status, ...)` (e.g. the `verified` email gate's 403, a manual
     * 409) — map to its own status with a status-derived slug, never a 500.
     */
    private static function httpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        $slug = match ($status) {
            400 => 'bad-request',
            403 => 'forbidden',
            404 => 'not-found',
            405 => 'method-not-allowed',
            409 => 'conflict',
            422 => 'unprocessable-entity',
            429 => 'rate-limited',
            default => 'http-error',
        };

        // Never echo an exception message on a 5xx — it may carry internals.
        $detail = $status < 500 && $e->getMessage() !== '' ? $e->getMessage() : null;

        return ProblemDetails::response(
            $status,
            $slug,
            $status >= 500 ? 'Server error.' : 'Request could not be processed.',
            $detail,
        )->withHeaders($e->getHeaders());
    }

    private static function critical(Throwable $e, string $slug): JsonResponse
    {
        Log::critical($slug, [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'request_id' => app()->bound('request_id') ? app('request_id') : null,
        ]);

        return ProblemDetails::response(500, $slug, 'Internal server error.');
    }
}
