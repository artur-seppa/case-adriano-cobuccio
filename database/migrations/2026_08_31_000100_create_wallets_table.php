<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('type', 16);
            $table->foreignUlid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference', 64)->nullable();
            $table->char('currency', 3)->default('BRL');
            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('entry_count')->default(0);
            $table->timestamps();

            $table->unique('reference');
        });

        DB::statement("ALTER TABLE wallets ADD CONSTRAINT wallets_type_chk CHECK (type IN ('user','system'))");
        DB::statement('CREATE UNIQUE INDEX wallets_user_id_unique ON wallets (user_id) WHERE user_id IS NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE wallets ADD CONSTRAINT wallets_identity_chk CHECK (
              (type = 'user'   AND user_id IS NOT NULL AND reference IS NULL) OR
              (type = 'system' AND user_id IS NULL     AND reference IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
