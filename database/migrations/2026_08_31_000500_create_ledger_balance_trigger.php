<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_transaction_balanced() RETURNS trigger AS $$
            DECLARE imbalance bigint;
            BEGIN
              SELECT COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_cents ELSE -amount_cents END), 0)
                INTO imbalance
                FROM ledger_entries
                WHERE transaction_id = COALESCE(NEW.transaction_id, OLD.transaction_id);
              IF imbalance <> 0 THEN
                RAISE EXCEPTION 'Ledger imbalance for transaction %: %',
                  COALESCE(NEW.transaction_id, OLD.transaction_id), imbalance
                  USING ERRCODE = 'check_violation';
              END IF;
              RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE CONSTRAINT TRIGGER ledger_entries_balanced
              AFTER INSERT OR UPDATE OR DELETE ON ledger_entries
              DEFERRABLE INITIALLY DEFERRED
              FOR EACH ROW EXECUTE FUNCTION assert_transaction_balanced();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_balanced ON ledger_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS assert_transaction_balanced()');
    }
};
