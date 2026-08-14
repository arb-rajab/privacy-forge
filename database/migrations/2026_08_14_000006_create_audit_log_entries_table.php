<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tamper-evidence design per ADR-0003. `sequence` is a plain auto-
// incrementing column used only to give the hash chain a deterministic,
// gap-free write order — the uuid `id` (matching AUDIT_LOG_ENTRY in
// 04-data-model.md) is not itself sortable, and PostgreSQL doesn't
// guarantee same-millisecond timestamps are distinct. No `updated_at`:
// entries are never updated after insert (application layer — see the
// note on consent_notices' migration re: the DB-grant half of this ADR
// not yet being implemented, since the app's DB role also owns the
// table).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('sequence')->unique();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('actor_type', ['staff', 'connector', 'system', 'data_subject']);
            $table->string('action');
            $table->string('resource_type');
            $table->uuid('resource_id');
            $table->uuid('policy_id')->nullable();
            $table->enum('decision', ['allow', 'deny'])->default('allow');
            $table->string('prev_hash')->nullable();
            $table->string('entry_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'resource_id', 'created_at']);
            $table->index('created_at');
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS audit_log_entries_sequence_seq OWNED BY audit_log_entries.sequence');
        DB::statement("ALTER TABLE audit_log_entries ALTER COLUMN sequence SET DEFAULT nextval('audit_log_entries_sequence_seq')");
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_entries');
        DB::statement('DROP SEQUENCE IF EXISTS audit_log_entries_sequence_seq');
    }
};
