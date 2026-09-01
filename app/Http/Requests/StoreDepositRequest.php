<?php

namespace App\Http\Requests;

use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\ValueObjects\Money;
use App\Rules\AsMoney;
use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', new AsMoney],
            'currency' => ['required', 'in:BRL'],
            'funding_method' => ['nullable', 'in:pix,boleto'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDeposit(): DepositData
    {
        return new DepositData(
            userId: $this->user()->id,
            amount: Money::fromDecimalString($this->string('amount')->value(), $this->string('currency')->value()),
            idempotencyKey: $this->header('Idempotency-Key'),
            description: $this->input('description'),
            fundingMethod: $this->input('funding_method'),
            metadata: $this->requestMetadata(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function requestMetadata(): array
    {
        return array_filter([
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'request_id' => $this->attributes->get('request_id'),
            'channel' => 'api',
        ], fn ($v) => $v !== null);
    }
}
