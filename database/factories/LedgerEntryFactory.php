<?php

namespace Database\Factories;

use App\Domain\Wallet\Enums\EntryDirection;
use App\Domain\Wallet\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'direction' => EntryDirection::Debit,
            'amount_cents' => 1000,
            'currency' => 'BRL',
            'balance_after_cents' => 0,
            'sequence' => 1,
            'created_at' => now(),
        ];
    }

    public function debit(): static
    {
        return $this->state(fn () => ['direction' => EntryDirection::Debit]);
    }

    public function credit(): static
    {
        return $this->state(fn () => ['direction' => EntryDirection::Credit]);
    }
}
