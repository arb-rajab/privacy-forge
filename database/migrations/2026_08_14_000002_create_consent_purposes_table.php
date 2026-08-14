<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// current_notice_id is added without a foreign key constraint here
// deliberately: consent_notices.purpose_id references this table, so the
// FK the other direction is added in a later migration
// (add_current_notice_foreign_to_consent_purposes_table) once
// consent_notices exists — an expand-first pattern per the migration
// approach documented in 04-data-model.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_purposes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('lawful_basis', [
                'consent',
                'contract',
                'legal_obligation',
                'vital_interests',
                'public_task',
                'legitimate_interests',
            ]);
            $table->enum('status', ['active', 'deprecated'])->default('active');
            $table->uuid('current_notice_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_purposes');
    }
};
