<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->char('id', 26);
            $table->string('type', 16);
            $table->foreignUlid('initiator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('source_wallet_id', 26);
            $table->char('destination_wallet_id', 26);
            $table->bigInteger('amount_cents');
            $table->char('currency', 3)->default('BRL');
            $table->char('reversal_of_transaction_id', 26)->nullable();
            $table->string('reversal_reason', 16)->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->string('description', 255)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('created_at');

            // Declared before the foreign keys so the self-referential FK on
            // reversal_of_transaction_id finds the primary key already in place.
            $table->primary('id');

            $table->foreign('source_wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            $table->foreign('destination_wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            $table->foreign('reversal_of_transaction_id')->references('id')->on('transactions')->restrictOnDelete();

            $table->index(['source_wallet_id', 'created_at']);
            $table->index(['destination_wallet_id', 'created_at']);
            $table->index(['initiator_id', 'created_at']);
            $table->index('type');
        });

        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_chk CHECK (type IN ('deposit','transfer','reversal'))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_chk CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_distinct_wallets_chk CHECK (source_wallet_id <> destination_wallet_id)');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_reason_chk CHECK (reversal_reason IS NULL OR reversal_reason IN ('inconsistency','user_request','fraud','duplicate'))");
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_reversal_shape_chk CHECK (type <> \'reversal\' OR (reversal_of_transaction_id IS NOT NULL AND reversal_reason IS NOT NULL))');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_non_reversal_chk CHECK (type = \'reversal\' OR reversal_of_transaction_id IS NULL)');
        DB::statement('CREATE UNIQUE INDEX transactions_reversal_of_unique ON transactions (reversal_of_transaction_id) WHERE reversal_of_transaction_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX transactions_idempotency_key_unique ON transactions (idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
