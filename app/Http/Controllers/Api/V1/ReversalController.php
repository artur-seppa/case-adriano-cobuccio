<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReversalRequest;
use App\Http\Resources\TransactionResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;

class ReversalController extends Controller
{
    /**
     * The `Transaction $transaction` hint is what makes the `{transaction}` route
     * segment resolve to a model (implicit binding → 404 on an unknown id) and
     * be available to StoreReversalRequest::authorize(); the id itself reaches
     * the action through the DTO the request builds.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Client-generated UUID; a retry with the same key + body replays the first response.', required: true, example: '3f0d2c1a-8b6e-4a7f-9c2d-1e5b7a9d0c34')]
    public function __invoke(StoreReversalRequest $request, Transaction $transaction, ReverseTransaction $reverse): JsonResponse
    {
        $reversal = $reverse($request->toReversal());

        return (new TransactionResource($reversal->load(
            'sourceWallet.user:id,name,email',
            'reversalTransaction:id,reversal_of_transaction_id',
            'destinationWallet.user:id,name,email',
        )))->response()->setStatusCode(201);
    }
}
