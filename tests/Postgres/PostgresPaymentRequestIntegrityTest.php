<?php

namespace Tests\Postgres;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Throwable;

class PostgresPaymentRequestIntegrityTest extends TestCase
{
    public function test_postgres_trigger_blocks_direct_exchange_rate_snapshot_updates(): void
    {
        $this->configurePostgresConnection();

        $connection = DB::connection('pgsql');

        try {
            $connection->getPdo();
            $this->assertTrue($connection->getSchemaBuilder()->hasTable('payment_requests'));
        } catch (Throwable $exception) {
            $this->markTestSkipped('PostgreSQL is not available or payment request migrations have not been run.');
        }

        $connection->beginTransaction();

        try {
            $paymentRequestId = $this->createPostgresPaymentRequest($connection);

            $connection->table('payment_requests')
                ->where('id', $paymentRequestId)
                ->update(['review_note' => 'Allowed direct update.']);

            $this->expectException(QueryException::class);
            $this->expectExceptionMessage('Payment request exchange rate data cannot be changed after creation.');

            $connection->table('payment_requests')
                ->where('id', $paymentRequestId)
                ->update(['eur_exchange_rate' => '999.00000000']);
        } finally {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    private function configurePostgresConnection(): void
    {
        config([
            'database.connections.pgsql.host' => env('CURRENCYFLOW_PGSQL_HOST', env('DB_HOST', 'postgres')),
            'database.connections.pgsql.port' => env('CURRENCYFLOW_PGSQL_PORT', env('DB_PORT', '5432')),
            'database.connections.pgsql.database' => env('CURRENCYFLOW_PGSQL_DATABASE', 'currencyflow'),
            'database.connections.pgsql.username' => env('CURRENCYFLOW_PGSQL_USERNAME', env('DB_USERNAME', 'currencyflow')),
            'database.connections.pgsql.password' => env('CURRENCYFLOW_PGSQL_PASSWORD', env('DB_PASSWORD', 'secret')),
        ]);

        DB::purge('pgsql');
    }

    private function createPostgresPaymentRequest($connection): string
    {
        $now = now()->format('Y-m-d H:i:s');
        $roleId = $this->lookupId($connection, 'roles', 'name', 'employee', [
            'description' => 'Employee role used by PostgreSQL trigger integration test.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $countryId = $this->lookupId($connection, 'countries', 'code', 'ZZ', [
            'name' => 'PostgreSQL Test Country',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $currencyId = $this->lookupId($connection, 'currencies', 'code', 'ZZZ', [
            'name' => 'PostgreSQL Test Currency',
            'exponent' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $statusId = $this->lookupId($connection, 'payment_request_statuses', 'name', 'pending', [
            'description' => 'Payment request is waiting for finance review.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $exchangeRateSourceId = $this->lookupId($connection, 'exchange_rate_sources', 'name', 'PostgreSQL Trigger Test', [
            'description' => 'Exchange rate source used by PostgreSQL trigger integration test.',
            'base_url' => 'https://example.test',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $userId = (string) Str::uuid();

        $connection->table('users')->insert([
            'id' => $userId,
            'role_id' => $roleId,
            'country_id' => $countryId,
            'preferred_currency_id' => $currencyId,
            'name' => 'PostgreSQL Trigger User',
            'email' => 'pgsql-trigger-'.Str::uuid().'@example.com',
            'password' => 'password',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $paymentRequestId = (string) Str::uuid();

        $connection->table('payment_requests')->insert([
            'id' => $paymentRequestId,
            'requester_id' => $userId,
            'currency_id' => $currencyId,
            'status_id' => $statusId,
            'exchange_rate_source_id' => $exchangeRateSourceId,
            'title' => 'PostgreSQL trigger test',
            'description' => 'Payment request created inside a rollback-only integration test.',
            'amount' => '100.0000',
            'eur_exchange_rate' => '2.00000000',
            'amount_eur' => '50.0000',
            'exchange_rate_fetched_at' => $now,
            'expires_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $paymentRequestId;
    }

    private function lookupId($connection, string $table, string $column, string $value, array $attributes): string
    {
        $existingId = $connection->table($table)->where($column, $value)->value('id');

        if ($existingId) {
            return $existingId;
        }

        $id = (string) Str::uuid();

        $connection->table($table)->insert([
            'id' => $id,
            $column => $value,
            ...$attributes,
        ]);

        return $id;
    }
}
