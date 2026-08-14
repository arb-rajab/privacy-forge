<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CONSENT_NOTICE is immutable once published (04-data-model.md invariant):
// no update endpoint exists at the application layer, and this table
// intentionally has no updated_at column, so there is nothing to touch
// after insert. See docs/project-memory/12-session-handoff.md for why the
// DB-grant half of that invariant (REVOKE UPDATE on body/published_at) is
// not implemented in this migration — the app's DB role is also the
// migration/owning role in the current docker-compose/CI setup, and
// PostgreSQL table owners retain implicit privileges regardless of REVOKE.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purpose_id')->constrained('consent_purposes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('body');
            $table->timestamp('published_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['purpose_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_notices');
    }
};
