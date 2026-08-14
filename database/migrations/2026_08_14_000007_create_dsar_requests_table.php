<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// DSAR_REQUEST per 04-data-model.md. subject_identifier uses the model's
// `encrypted` cast (reversible) — unlike ConsentRecord's one-way hash,
// staff genuinely need to read the identity claim to perform the
// manual-verification stub (FR-020). subject_identifier_hash is a
// separate HMAC column used only for rate-limit lookups (NFR-006),
// mirroring ConsentRecord::hashIdentifier(). status_token is an opaque,
// unguessable value used exclusively by the public status-check link
// (T-05, 06-security-threat-model.md) — the row's own uuid `id` is never
// exposed to an unauthenticated caller. erasure_approved_by/at columns
// are part of the authoritative DSAR_REQUEST schema but are not
// populated by any endpoint this session — erasure approval is deferred,
// see docs/project-memory/12-session-handoff.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsar_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('subject_identifier');
            $table->string('subject_identifier_hash');
            $table->string('status_token')->unique();
            $table->enum('request_type', ['access', 'export', 'erasure']);
            $table->enum('status', ['pending_verification', 'in_progress', 'partially_complete', 'complete', 'rejected'])->default('pending_verification');
            $table->foreignUuid('identity_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignUuid('erasure_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('erasure_approved_at')->nullable();
            $table->timestamps();

            $table->index('subject_identifier_hash');
            $table->index(['status', 'created_at']);
        });

        // A DSAR cannot reach in_progress without identity verification
        // recorded (FR-007, 04-data-model.md invariant) — enforced at the
        // DB level as well as in the application layer
        // (Admin\DsarController), so a bug or a direct DB write can't
        // silently violate it.
        DB::statement(<<<'SQL'
            ALTER TABLE dsar_requests
            ADD CONSTRAINT dsar_requests_verified_before_in_progress
            CHECK (status <> 'in_progress' OR (identity_verified_by IS NOT NULL AND identity_verified_at IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dsar_requests');
    }
};
