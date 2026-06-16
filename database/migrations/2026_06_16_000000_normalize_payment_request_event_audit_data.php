<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_request_events', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_request_events', 'from_status_id')) {
                $table->foreignUuid('from_status_id')->nullable()->constrained('payment_request_statuses')->restrictOnDelete();
            }

            if (! Schema::hasColumn('payment_request_events', 'to_status_id')) {
                $table->foreignUuid('to_status_id')->nullable()->constrained('payment_request_statuses')->restrictOnDelete();
            }

            if (! Schema::hasColumn('payment_request_events', 'note')) {
                $table->text('note')->nullable();
            }
        });

        if (Schema::hasColumn('payment_request_events', 'metadata')) {
            Schema::table('payment_request_events', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_request_events', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_request_events', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        Schema::table('payment_request_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_status_id');
            $table->dropConstrainedForeignId('to_status_id');
            $table->dropColumn('note');
        });
    }
};
