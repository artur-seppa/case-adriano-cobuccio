<?php

namespace App\Http\Requests;

use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;

class StoreReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reverse', $this->route('transaction'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** @example "Cobrança em duplicidade" */
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function toReversal(): ReversalData
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return new ReversalData(
            transactionId: $transaction->id,
            reason: ReversalReason::UserRequest,
            initiatedByUserId: $this->user()->id,
            note: $this->input('note'),
            metadata: array_filter([
                'ip' => $this->ip(),
                'request_id' => $this->attributes->get('request_id'),
                'reversed_by' => $this->user()->id,
                'channel' => 'api',
            ], fn ($v) => $v !== null),
        );
    }
}
