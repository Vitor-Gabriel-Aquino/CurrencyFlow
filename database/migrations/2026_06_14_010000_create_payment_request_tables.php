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
        Schema::create('payment_request_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('payment_request_event_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('exchange_rate_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description');
            $table->string('base_url');
            $table->timestamps();
        });

        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('currency_id')->constrained('currencies')->restrictOnDelete();
            $table->foreignUuid('status_id')->constrained('payment_request_statuses')->restrictOnDelete();
            $table->foreignUuid('exchange_rate_source_id')->constrained('exchange_rate_sources')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 19, 4);
            $table->decimal('exchange_rate_to_eur', 19, 8);
            $table->decimal('amount_eur', 19, 4);
            $table->timestamp('exchange_rate_fetched_at');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['requester_id', 'created_at']);
            $table->index(['status_id', 'expires_at']);
        });

        Schema::create('payment_request_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('event_type_id')->constrained('payment_request_event_types')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_request_events');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('exchange_rate_sources');
        Schema::dropIfExists('payment_request_event_types');
        Schema::dropIfExists('payment_request_statuses');
    }
};
