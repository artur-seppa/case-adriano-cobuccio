<?php

namespace App\Http\Resources;

use App\Domain\Wallet\Models\LedgerEntry;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerEntry */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'sequence' => $this->sequence,
            'direction' => $this->direction->value,
            'amount' => $this->amount->decimalString(),
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => $this->amount->formatBRL(),
            'balance_after' => number_format($this->balance_after_cents / 100, 2, '.', ''),
            'balance_after_cents' => $this->balance_after_cents,
            'transaction' => [
                'id' => $this->transaction_id,
                'type' => $this->whenLoaded('transaction', fn () => $this->transaction->type->value),
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
