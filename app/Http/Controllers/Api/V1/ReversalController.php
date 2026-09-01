<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReversalRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

class ReversalController extends Controller
{
    public function __invoke(StoreReversalRequest $request, Transaction $transaction, ReverseTransaction $reverse): JsonResponse
    {
        $reversal = $reverse($request->toReversal());

        return (new TransactionResource($reversal->load(
            'sourceWallet.user:id,name,email',
            'destinationWallet.user:id,name,email',
        )))->response()->setStatusCode(201);
    }
}
