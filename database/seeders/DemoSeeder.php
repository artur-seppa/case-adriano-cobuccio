<?php

namespace Database\Seeders;

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\RegisterUser;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A walkthrough-ready dataset: three verified users, each with a funded wallet,
 * a handful of transfers between them, and one reversal — all created through
 * the real domain Actions so the ledger stays consistent (run `wallet:reconcile`
 * after and it passes).
 *
 * Runs automatically after a `migrate --seed` in local; also `php artisan
 * db:seed --class="Database\Seeders\DemoSeeder"`. Refuses to touch production
 * and skips itself if the demo users already exist.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Password1234';

    private const USERS = [
        ['name' => 'Alice Souza', 'email' => 'alice@wallet.test'],
        ['name' => 'Bruno Lima', 'email' => 'bruno@wallet.test'],
        ['name' => 'Carla Dias', 'email' => 'carla@wallet.test'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoSeeder skipped: not for production.');

            return;
        }

        if (User::where('email', self::USERS[0]['email'])->exists()) {
            $this->command?->info('DemoSeeder skipped: demo users already present.');

            return;
        }

        // Spread the timeline so the statement/list look real instead of all "now".
        Carbon::setTestNow(now()->subDays(7));

        try {
            [$alice, $bruno, $carla] = $this->createUsers();

            $this->deposit($alice, '500.00');
            $this->deposit($bruno, '200.00');
            $this->deposit($carla, '1000.00');

            Carbon::setTestNow(now()->addDays(2));
            $this->transfer($alice, $bruno, '120.00', 'Aluguel');
            $this->transfer($carla, $alice, '300.00', 'Reembolso');

            Carbon::setTestNow(now()->addDays(3));
            $lunch = $this->transfer($bruno, $carla, '50.00', 'Rateio do almoço');

            // Bruno regrets the lunch split and reverses his own transfer.
            $this->reverse($lunch->id, $bruno->id, 'Valor errado, refeito depois.');
        } finally {
            Carbon::setTestNow(null);
        }

        $this->command?->info(sprintf(
            'DemoSeeder: 3 users (%s), 3 deposits, 3 transfers, 1 reversal. Password: %s',
            collect(self::USERS)->pluck('email')->implode(', '),
            self::PASSWORD,
        ));
    }

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function createUsers(): array
    {
        return collect(self::USERS)->map(function (array $data): User {
            $user = app(RegisterUser::class)([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        })->all();
    }

    private function deposit(User $user, string $amount): void
    {
        app(DepositFunds::class)(new DepositData(
            userId: $user->id,
            amount: Money::fromDecimalString($amount),
            description: 'Aporte inicial',
            metadata: ['channel' => 'seed'],
        ));
    }

    private function transfer(User $from, User $to, string $amount, string $description): Transaction
    {
        return app(TransferFunds::class)(new TransferData(
            senderId: $from->id,
            recipientId: $to->id,
            amount: Money::fromDecimalString($amount),
            description: $description,
            metadata: ['channel' => 'seed'],
        ));
    }

    private function reverse(string $transactionId, string $byUserId, string $note): void
    {
        app(ReverseTransaction::class)(new ReversalData(
            transactionId: $transactionId,
            reason: ReversalReason::UserRequest,
            initiatedByUserId: $byUserId,
            note: $note,
            metadata: ['channel' => 'seed'],
        ));
    }
}
