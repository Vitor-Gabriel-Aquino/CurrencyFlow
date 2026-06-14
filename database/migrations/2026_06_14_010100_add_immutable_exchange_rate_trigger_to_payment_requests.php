<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_payment_request_exchange_rate_changes()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.currency_id IS DISTINCT FROM NEW.currency_id
                    OR OLD.amount IS DISTINCT FROM NEW.amount
                    OR OLD.exchange_rate_source_id IS DISTINCT FROM NEW.exchange_rate_source_id
                    OR OLD.exchange_rate_to_eur IS DISTINCT FROM NEW.exchange_rate_to_eur
                    OR OLD.amount_eur IS DISTINCT FROM NEW.amount_eur
                    OR OLD.exchange_rate_fetched_at IS DISTINCT FROM NEW.exchange_rate_fetched_at
                THEN
                    RAISE EXCEPTION 'Payment request exchange rate data cannot be changed after creation.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            DROP TRIGGER IF EXISTS payment_request_exchange_rate_immutable ON payment_requests
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER payment_request_exchange_rate_immutable
            BEFORE UPDATE ON payment_requests
            FOR EACH ROW
            EXECUTE FUNCTION prevent_payment_request_exchange_rate_changes()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS payment_request_exchange_rate_immutable ON payment_requests');
        DB::statement('DROP FUNCTION IF EXISTS prevent_payment_request_exchange_rate_changes()');
    }
};
