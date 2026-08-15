<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DELETION_CERTIFICATE per 04-data-model.md (US-009/FR-011).
// `retention_execution_id` is part of the authoritative ERD (a
// certificate can also be produced by scheduled retention execution,
// US-012) but that path doesn't exist yet — left nullable here, populated
// only by DsarCompletionEvaluator's erasure path this session.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dsar_request_id')->nullable()->constrained('dsar_requests')->cascadeOnDelete();
            $table->uuid('retention_execution_id')->nullable();
            $table->text('summary');
            $table->text('exceptions')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_certificates');
    }
};
