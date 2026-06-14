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
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('code', 2)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('exponent');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('role_id')->after('id')->constrained()->restrictOnDelete();
            $table->foreignUuid('country_id')->after('role_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('preferred_currency_id')->after('country_id')->constrained('currencies')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_currency_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('currencies');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('roles');
    }
};
