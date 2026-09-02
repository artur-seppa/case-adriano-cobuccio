<?php

namespace App\Http\Resources;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $userId = $request->user()?->id;
        $ownsSource = $this->sourceWallet?->user_id === $userId;
        $ownsDestination = $this->destinationWallet?->user_id === $userId;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->isReversed() ? 'reversed' : 'completed',
            'amount' => $this->amount->decimalString(),
            'amount_cents' => $this->amount_cents,
            'amount_formatted' => $this->amount->formatBRL(),
            'currency' => $this->currency,
            'direction' => match (true) {
                $ownsSource && $ownsDestination => 'self',
                $ownsSource => 'out',
                default => 'in',
            },
            'counterparty' => $this->counterparty($ownsSource),
            'description' => $this->description,
            'reversal' => [
                'is_reversed' => $this->isReversed(),
                'reversal_of_transaction_id' => $this->reversal_of_transaction_id,
                'reversed_by_transaction_id' => $this->whenLoaded(
                    'reversalTransaction',
                    fn () => $this->reversalTransaction?->id,
                ),
                'reason' => $this->reversal_reason?->value,
            ],
            'created_at' => $this->created_at->toIso8601String(),
            'metadata' => collect($this->metadata ?? [])->only(['channel', 'funding_method', 'note'])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function counterparty(bool $ownsSource): array
    {
        if ($this->type === TransactionType::Deposit) {
            return ['label' => 'Depósito'];
        }

        $other = $ownsSource ? $this->destinationWallet?->user : $this->sourceWallet?->user;

        return $other
            ? ['name' => $other->name, 'email' => $other->email, 'label' => $other->name]
            : ['label' => 'Sistema'];
    }
}
