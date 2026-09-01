<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Actions\DepositFunds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepositRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

class DepositController extends Controller
{
    public function __invoke(StoreDepositRequest $request, DepositFunds $deposit): JsonResponse
    {
        $transaction = $deposit($request->toDeposit());

        return (new TransactionResource($transaction->load(
            'sourceWallet.user:id,name,email',
            'reversalTransaction:id,reversal_of_transaction_id',
            'destinationWallet.user:id,name,email',
        )))->response()->setStatusCode(201);
    }
}
