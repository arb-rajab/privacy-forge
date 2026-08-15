<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// EXPORT_BUNDLE per 04-data-model.md (US-008/FR-010). One row per
// (dsar_request, format) — a completed export produces two rows (json,
// csv) sharing the same TTL window. `download_token` extends the ERD the
// same way DSAR_REQUEST's `status_token` did (T-05,
// 06-security-threat-model.md: "export access [is] keyed only by
// unguessable signed tokens, not sequential/plain DSAR IDs") — the row's
// own uuid `id` is never exposed to an unauthenticated data subject.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dsar_request_id')->constrained('dsar_requests')->cascadeOnDelete();
            $table->string('download_token')->unique();
            $table->string('storage_path');
            $table->enum('format', ['json', 'csv']);
            $table->timestamp('signed_url_expires_at');
            $table->timestamps();

            $table->index(['dsar_request_id']);
        });

        // NFR-007: never more than 72 hours past creation — enforced at
        // creation in application code (ExportBundleAssembler) and again
        // here as a defence-in-depth DB check, since 04-data-model.md's
        // invariant table is explicit that this must hold "regardless of
        // what the row claims", not just at the moment the row is written.
        DB::statement(<<<'SQL'
            ALTER TABLE export_bundles
            ADD CONSTRAINT export_bundles_ttl_max_72h
            CHECK (signed_url_expires_at <= created_at + INTERVAL '72 hours')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('export_bundles');
    }
};
