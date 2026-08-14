<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// POLICY_DEFINITION per 04-data-model.md and ADR-0001 — policies as
// versioned database rows, evaluated by App\Services\PolicyEvaluator.
// No seeding/bootstrap mechanism exists yet for initial rows
// (deliberately out of scope this session — see
// docs/project-memory/12-session-handoff.md); tests create rows
// directly via PolicyDefinitionFactory.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('action_name');
            $table->unsignedInteger('version')->default(1);
            $table->json('subject_conditions')->nullable();
            $table->json('resource_conditions')->nullable();
            $table->json('environment_conditions')->nullable();
            $table->enum('effect', ['allow', 'deny'])->default('allow');
            $table->enum('status', ['active', 'superseded'])->default('active');
            $table->timestamps();

            $table->index(['action_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_definitions');
    }
};
