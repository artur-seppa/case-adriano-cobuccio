<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Actions\TransferFunds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    public function __invoke(StoreTransferRequest $request, TransferFunds $transfer): JsonResponse
    {
        $transaction = $transfer($request->toTransfer());

        return (new TransactionResource($transaction->load(
            'sourceWallet.user:id,name,email',
            'destinationWallet.user:id,name,email',
        )))->response()->setStatusCode(201);
    }
}
