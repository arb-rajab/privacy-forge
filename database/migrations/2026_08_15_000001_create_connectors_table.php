<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CONNECTOR per 04-data-model.md (ADR-0004). `secret_hash` is the ERD's
// column name, but it is implemented with Laravel's `encrypted` cast
// (reversible), the same choice already made for DsarRequest's
// subject_identifier: the application must recompute the exact HMAC the
// connector computes (to verify inbound callbacks, T-07) and must itself
// sign outbound webhooks with the same shared secret, both of which are
// impossible from a one-way hash. A true one-way hash would only work for
// bearer-token *comparison*, not HMAC signing on both sides.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('webhook_url');
            $table->text('secret_hash');
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
