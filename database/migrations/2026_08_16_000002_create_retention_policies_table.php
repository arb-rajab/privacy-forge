<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RETENTION_POLICY per 04-data-model.md (US-010/FR-012). Versioned the
// same way PolicyDefinition is (04-data-model.md's migration approach):
// `data_category_id` is the grouping key across versions (mirroring
// PolicyDefinition's `action_name`) — updating a policy supersedes the
// current active row for that data category and creates version+1, never
// mutates in place.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('data_category_id')->constrained('data_categories');
            $table->unsignedInteger('retention_period_days');
            $table->enum('post_expiry_action', ['erase', 'anonymise']);
            $table->enum('status', ['active', 'deprecated'])->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['data_category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
