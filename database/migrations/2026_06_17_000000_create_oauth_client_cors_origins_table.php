<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_client_cors_origins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('origin');
            $table->timestamps();

            $table->unique(['oauth_client_id', 'origin']);
            $table->index('origin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_client_cors_origins');
    }

    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
