<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RETENTION_EXECUTION per 04-data-model.md (US-011/US-012, ADR-0002). A
// single dry-run or real run of a policy — ADR-0002's consequences are
// explicit that a dry run is not "free": it produces this row (mode=
// dry_run, certificate_id stays null) just as a real run produces both
// this row (mode=real) and a DELETION_CERTIFICATE, so both paths are
// auditable, not just the real one. `certificate_id` is nullable here
// (a dry run never sets it); the FK is added once deletion_certificates
// gains a matching constrained retention_execution_id column in the next
// migration, since deletion_certificates already exists (Session 8) and
// this table does not exist yet at that point — expand-first, same reason
// consent_purposes.current_notice_id was added via a later migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('retention_policy_id')->constrained('retention_policies');
            $table->enum('mode', ['dry_run', 'real']);
            $table->unsignedInteger('affected_record_count');
            $table->foreignUuid('certificate_id')->nullable()->constrained('deletion_certificates')->nullOnDelete();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['retention_policy_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_executions');
    }
};
