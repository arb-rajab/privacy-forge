<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_purposes', function (Blueprint $table) {
            $table->foreign('current_notice_id')->references('id')->on('consent_notices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consent_purposes', function (Blueprint $table) {
            $table->dropForeign(['current_notice_id']);
        });
    }
};
