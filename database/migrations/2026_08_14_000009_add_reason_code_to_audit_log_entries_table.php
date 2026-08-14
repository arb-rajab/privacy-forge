<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADR-0006: every fail-closed denial carries a reason code distinguishing
// "denied by design" from "the evaluator itself is broken" (e.g.
// policy_missing, evaluation_error); ordinary ABAC denials get
// policy_conditions_not_met. Added now because dsar.identity.verify
// (this session) is the first sensitive action to actually produce these
// decisions — see App\Services\AuditLogger::record().
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->string('reason_code')->nullable()->after('decision');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->dropColumn('reason_code');
        });
    }
};
