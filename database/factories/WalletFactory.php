<?php

namespace Database\Factories;

use App\Domain\Wallet\Enums\WalletType;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'type' => WalletType::User,
            'user_id' => User::factory(),
            'reference' => null,
            'currency' => 'BRL',
            'balance_cents' => 0,
            'entry_count' => 0,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id, 'type' => WalletType::User, 'reference' => null]);
    }

    public function system(string $reference = 'external_world'): static
    {
        return $this->state(fn () => [
            'type' => WalletType::System, 'user_id' => null, 'reference' => $reference,
        ]);
    }

    public function withBalanceCents(int $cents): static
    {
        return $this->state(fn () => ['balance_cents' => $cents]);
    }
}
