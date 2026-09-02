<?php

namespace App\Http\Resources;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'balance' => $this->balance->decimalString(),
            'balance_cents' => $this->balance_cents,
            'balance_formatted' => $this->balance->formatBRL(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
