<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    private const EAGER = [
        'sourceWallet:id,user_id',
        'sourceWallet.user:id,name,email',
        'destinationWallet:id,user_id',
        'destinationWallet.user:id,name,email',
        'reversalTransaction:id,reversal_of_transaction_id',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'type' => ['sometimes', 'in:deposit,transfer,reversal'],
            'direction' => ['sometimes', 'in:in,out'],
            'status' => ['sometimes', 'in:completed,reversed'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $userId = $request->user()->id;
        $walletIds = $request->user()->wallet()->pluck('id')->all();

        $query = Transaction::query()
            ->with(self::EAGER)
            ->where(function ($q) use ($userId, $walletIds) {
                $q->where('initiator_id', $userId)
                    ->orWhereIn('source_wallet_id', $walletIds)
                    ->orWhereIn('destination_wallet_id', $walletIds);
            });

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['from'])) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if (isset($filters['to'])) {
            $query->where('created_at', '<=', $request->date('to'));
        }
        if (isset($filters['direction'])) {
            $column = $filters['direction'] === 'out' ? 'source_wallet_id' : 'destination_wallet_id';
            $query->whereIn($column, $walletIds);
        }
        if (($filters['status'] ?? null) === 'reversed') {
            $query->whereHas('reversalTransaction');
        } elseif (($filters['status'] ?? null) === 'completed') {
            $query->whereDoesntHave('reversalTransaction');
        }

        $page = $query->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 20);

        return TransactionResource::collection($page);
    }

    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(self::EAGER);

        return new TransactionResource($transaction);
    }
}
