<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Delete expired idempotency keys (spec §8, expires_at ~24h).';

    public function handle(): int
    {
        $deleted = DB::table('idempotency_keys')->where('expires_at', '<', now())->delete();

        $this->info("Pruned {$deleted} expired idempotency key(s).");

        return self::SUCCESS;
    }
}
