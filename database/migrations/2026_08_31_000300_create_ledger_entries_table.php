<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('transaction_id', 26);
            $table->char('wallet_id', 26);
            $table->string('direction', 6);
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('BRL');
            $table->bigInteger('balance_after_cents');
            $table->bigInteger('sequence');
            $table->timestamp('created_at');

            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
            $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();

            $table->unique(['transaction_id', 'wallet_id', 'direction']);
            $table->unique(['wallet_id', 'sequence']);
            $table->index(['wallet_id', 'sequence']);
            $table->index('transaction_id');
        });

        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_direction_chk CHECK (direction IN ('debit','credit'))");
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_chk CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
