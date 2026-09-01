<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('key')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->char('request_fingerprint', 64);
            $table->string('status', 12);
            $table->smallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->char('transaction_id', 26)->nullable();
            $table->timestamp('locked_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');

            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_status_chk CHECK (status IN ('locked','completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
