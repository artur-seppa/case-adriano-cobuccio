<?php

namespace App\Console\Commands;

use App\Domain\Wallet\Services\BalanceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile {--fix : Rewrite cached balances from the ledger}';

    protected $description = 'Verify wallet balances against the immutable ledger and the global zero-sum invariant.';

    public function handle(BalanceReconciler $reconciler): int
    {
        $result = $reconciler->check();

        if ($reconciler->isHealthy($result)) {
            $this->info('Reconciliation OK — no drift, global balance is zero.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            '%d wallet(s) drifted; global balance = %d (expected 0).',
            count($result['drifts']),
            $result['global_balance_cents'],
        ));

        $this->table(
            ['wallet_id', 'cached', 'computed', 'snapshot', 'diff'],
            $result['drifts'],
        );

        Log::critical('wallet.reconcile.drift', $result);

        if ($this->option('fix')) {
            foreach ($result['drifts'] as $drift) {
                DB::table('wallets')->where('id', $drift['wallet_id'])
                    ->update(['balance_cents' => $drift['computed']]);
            }
            Log::warning('wallet.reconcile.fixed', ['count' => count($result['drifts'])]);
            $this->warn('Cached balances rewritten from the ledger.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
