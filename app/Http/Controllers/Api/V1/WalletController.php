<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\WalletResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function show(Request $request): WalletResource
    {
        return new WalletResource($request->user()->wallet()->firstOrFail());
    }

    public function statement(Request $request): AnonymousResourceCollection
    {
        $entries = $request->user()->wallet()->firstOrFail()
            ->ledgerEntries()
            ->with('transaction:id,type')
            ->orderByDesc('sequence')
            ->cursorPaginate($request->integer('per_page', 20));

        return LedgerEntryResource::collection($entries);
    }
}
