<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Withdrawing consent is an UPDATE to status/withdrawn_at only, never a
// DELETE (04-data-model.md invariant) — enforced at the application layer
// (ConsentRecord model exposes no delete path). See the note on
// consent_notices' migration re: why the DB-grant half of this invariant
// (REVOKE DELETE) isn't implemented yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_identifier_hash');
            $table->foreignUuid('purpose_id')->constrained('consent_purposes');
            $table->foreignUuid('notice_id')->constrained('consent_notices');
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->timestamp('given_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['subject_identifier_hash', 'purpose_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};
