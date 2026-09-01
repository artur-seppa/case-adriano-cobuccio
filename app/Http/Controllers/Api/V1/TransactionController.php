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
        $userId = $request->user()->id;
        $walletIds = $request->user()->wallet()->pluck('id');

        $query = Transaction::query()
            ->with(self::EAGER)
            ->where(function ($q) use ($userId) {
                $q->where('initiator_id', $userId)
                    ->orWhereHas('sourceWallet', fn ($w) => $w->where('user_id', $userId))
                    ->orWhereHas('destinationWallet', fn ($w) => $w->where('user_id', $userId));
            });

        if ($type = $request->string('type')->value()) {
            $query->where('type', $type);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }
        if (($direction = $request->string('direction')->value()) && in_array($direction, ['in', 'out'], true)) {
            $column = $direction === 'out' ? 'source_wallet_id' : 'destination_wallet_id';
            $query->whereIn($column, $walletIds);
        }
        if (($status = $request->string('status')->value()) === 'reversed') {
            $query->whereHas('reversalTransaction');
        } elseif ($status === 'completed') {
            $query->whereDoesntHave('reversalTransaction');
        }

        $page = $query->orderByDesc('created_at')->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 20));

        return TransactionResource::collection($page);
    }

    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(self::EAGER);

        return new TransactionResource($transaction);
    }
}
