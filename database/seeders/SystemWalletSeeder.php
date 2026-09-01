<?php

namespace Database\Seeders;

use App\Domain\Wallet\Support\SystemWallets;
use Illuminate\Database\Seeder;

class SystemWalletSeeder extends Seeder
{
    public function run(): void
    {
        SystemWallets::externalWorld();
    }
}
