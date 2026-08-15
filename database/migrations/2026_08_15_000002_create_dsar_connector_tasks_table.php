<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DSAR_CONNECTOR_TASK per 04-data-model.md (ADR-0004/US-007). One row per
// (dsar_request, connector) pair, independently tracked per FR-008 —
// this table, not the parent DSAR row, is what makes partial connector
// failure visible (FR-009) rather than collapsed into a single status.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsar_connector_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dsar_request_id')->constrained('dsar_requests')->cascadeOnDelete();
            $table->foreignUuid('connector_id')->constrained('connectors')->cascadeOnDelete();
            $table->enum('task_type', ['export', 'erasure']);
            $table->enum('status', ['pending', 'success', 'failed', 'partial'])->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['dsar_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsar_connector_tasks');
    }
};
