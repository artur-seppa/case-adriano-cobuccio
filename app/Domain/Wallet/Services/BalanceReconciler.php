<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class BalanceReconciler
{
    /**
     * @return array{drifts: array<int, array{wallet_id: string, cached: int, computed: int, snapshot: int, diff: int}>, global_balance_cents: int}
     */
    public function check(): array
    {
        $drifts = [];

        Wallet::query()->orderBy('id')->chunkById(500, function ($wallets) use (&$drifts) {
            foreach ($wallets as $wallet) {
                $computed = (int) DB::table('ledger_entries')
                    ->where('wallet_id', $wallet->id)
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_cents ELSE -amount_cents END), 0) AS c")
                    ->value('c');

                $snapshot = (int) (DB::table('ledger_entries')
                    ->where('wallet_id', $wallet->id)
                    ->orderByDesc('sequence')
                    ->value('balance_after_cents') ?? 0);

                if ($wallet->balance_cents !== $computed || $wallet->balance_cents !== $snapshot) {
                    $drifts[] = [
                        'wallet_id' => $wallet->id,
                        'cached' => (int) $wallet->balance_cents,
                        'computed' => $computed,
                        'snapshot' => $snapshot,
                        'diff' => (int) $wallet->balance_cents - $computed,
                    ];
                }
            }
        });

        return [
            'drifts' => $drifts,
            'global_balance_cents' => (int) Wallet::query()->sum('balance_cents'),
        ];
    }

    /**
     * @param  array{drifts: array<int, mixed>, global_balance_cents: int}  $result
     */
    public function isHealthy(array $result): bool
    {
        return $result['drifts'] === [] && $result['global_balance_cents'] === 0;
    }
}
