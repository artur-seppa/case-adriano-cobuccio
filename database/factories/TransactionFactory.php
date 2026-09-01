<?php

namespace Database\Factories;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'type' => TransactionType::Deposit,
            'initiator_id' => null,
            'amount_cents' => 1000,
            'currency' => 'BRL',
            'reversal_of_transaction_id' => null,
            'reversal_reason' => null,
            'idempotency_key' => null,
            'description' => null,
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    public function deposit(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Deposit]);
    }

    public function transfer(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Transfer]);
    }

    public function reversal(): static
    {
        return $this->state(fn () => ['type' => TransactionType::Reversal]);
    }
}
