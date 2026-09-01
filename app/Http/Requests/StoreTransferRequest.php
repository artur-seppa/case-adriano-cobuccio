<?php

namespace App\Http\Requests;

use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use App\Rules\AsMoney;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    private ?User $recipient = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:255'],
            'amount' => ['required', new AsMoney],
            'currency' => ['required', 'in:BRL'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('recipient')) {
                return;
            }

            $raw = (string) $this->input('recipient');
            $this->recipient = filter_var($raw, FILTER_VALIDATE_EMAIL)
                ? User::where('email', $raw)->first()
                : User::whereKey($raw)->first();

            if (! $this->recipient) {
                $validator->errors()->add('recipient', 'No user matches that email or id.');

                return;
            }

            if ($this->recipient->is($this->user())) {
                $validator->errors()->add('recipient', 'You cannot transfer to yourself.');
            }
        });
    }

    public function toTransfer(): TransferData
    {
        return new TransferData(
            senderId: $this->user()->id,
            recipientId: $this->recipient->id,
            amount: Money::fromDecimalString($this->string('amount')->value(), $this->string('currency')->value()),
            idempotencyKey: $this->header('Idempotency-Key'),
            description: $this->input('description'),
            metadata: array_filter([
                'ip' => $this->ip(),
                'user_agent' => $this->userAgent(),
                'request_id' => $this->attributes->get('request_id'),
                'channel' => 'api',
            ], fn ($v) => $v !== null),
        );
    }
}
