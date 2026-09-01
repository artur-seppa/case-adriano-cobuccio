<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Actions\TransferFunds;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransactionResource;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Illuminate\Http\JsonResponse;

class TransferController extends Controller
{
    #[HeaderParameter('Idempotency-Key', description: 'Client-generated UUID; a retry with the same key + body replays the first response.', required: true, example: '3f0d2c1a-8b6e-4a7f-9c2d-1e5b7a9d0c34')]
    public function __invoke(StoreTransferRequest $request, TransferFunds $transfer): JsonResponse
    {
        $transaction = $transfer($request->toTransfer());

        return (new TransactionResource($transaction->load(
            'sourceWallet.user:id,name,email',
            'reversalTransaction:id,reversal_of_transaction_id',
            'destinationWallet.user:id,name,email',
        )))->response()->setStatusCode(201);
    }
}
