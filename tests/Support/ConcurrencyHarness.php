<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ConcurrencyHarness
{
    /**
     * Run $task in $workers forked child processes. Each child gets a fresh DB
     * connection (a PDO handle cannot be shared across fork) and reports its
     * outcome through a temp file the parent collects after reaping every child.
     *
     * @return array<int, array{ok: bool, error: string|null}>
     */
    public static function run(int $workers, Closure $task): array
    {
        $dir = storage_path('framework/testing/concurrency');
        @mkdir($dir, 0777, true);
        array_map('unlink', glob("{$dir}/*") ?: []);

        $pids = [];

        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('pcntl_fork failed');
            }

            if ($pid === 0) {
                DB::purge();
                DB::reconnect();

                $result = ['ok' => true, 'error' => null];
                try {
                    $task($i);
                } catch (Throwable $e) {
                    $result = ['ok' => false, 'error' => $e::class.': '.$e->getMessage()];
                }

                file_put_contents("{$dir}/{$i}.json", json_encode($result));
                exit(0);
            }

            $pids[$i] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $out = [];
        for ($i = 0; $i < $workers; $i++) {
            $out[$i] = json_decode((string) file_get_contents("{$dir}/{$i}.json"), true);
        }

        return $out;
    }
}
