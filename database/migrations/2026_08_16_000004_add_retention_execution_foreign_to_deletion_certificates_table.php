<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Closes the FK left unconstrained since Session 8
// (2026_08_15_000004_create_deletion_certificates_table.php: "left
// nullable here, populated only ... this session" — retention_execution_id
// existed as a plain uuid column with no constraint because
// retention_executions didn't exist yet). Now that it does (US-012), this
// migration both constrains the column and adds a CHECK enforcing the
// explicit decision (see docs/project-memory/09-decision-log.md,
// "Deletion certificate format — shared table, Session 11") that a
// DELETION_CERTIFICATE is produced by exactly one source — a DSAR
// erasure (US-009) or a retention execution (US-012), never both and
// never neither — so the two are structurally distinguishable by which
// FK is populated, without a separate "source" column that could drift
// out of sync with the FKs themselves.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deletion_certificates', function (Blueprint $table) {
            $table->foreign('retention_execution_id')
                ->references('id')->on('retention_executions')
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE deletion_certificates
            ADD CONSTRAINT deletion_certificates_exactly_one_source
            CHECK ((dsar_request_id IS NOT NULL)::int + (retention_execution_id IS NOT NULL)::int = 1)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE deletion_certificates DROP CONSTRAINT deletion_certificates_exactly_one_source');

        Schema::table('deletion_certificates', function (Blueprint $table) {
            $table->dropForeign(['retention_execution_id']);
        });
    }
};
