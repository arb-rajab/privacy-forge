<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DATA_CATEGORY per 04-data-model.md (US-010, first implementation —
// this entity was ERD-only before Session 11). `subject_table` extends
// the ERD's listed columns: it names which of this instance's own tables
// a retention policy governed by this category actually applies to, so
// RetentionSelector has a concrete query to run rather than an abstract
// "data category" with no wiring to a real table. Deliberately a closed
// enum of exactly the tables 04-data-model.md's own "Retention and
// deletion rules" section names as subject to the organisation's
// retention policies (consent_records, dsar_requests) — audit_log_entries
// and deletion_certificates are excluded by construction, not by a runtime
// check, because that section is explicit those two are evidentiary
// records with their own indefinite retention, never a retention-policy
// target.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('sensitivity', ['standard', 'elevated', 'special_category']);
            $table->enum('subject_table', ['consent_records', 'dsar_requests']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_categories');
    }
};
