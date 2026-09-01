<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Always: the system counterparty wallet the ledger needs (prod-safe).
        $this->call(SystemWalletSeeder::class);

        // Local only: a walkthrough dataset (users, wallets, transfers, a reversal).
        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
