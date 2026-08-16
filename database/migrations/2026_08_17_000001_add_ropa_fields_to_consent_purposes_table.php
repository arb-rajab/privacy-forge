<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Session 12 (US-013/FR-016, RoPA export). Art. 30(1)(c) requires a RoPA
// to state "the categories of data subjects and of the categories of
// personal data" per purpose — neither has any existing home in the data
// model: CONSENT_PURPOSE has no link to DATA_CATEGORY at all (the ERD
// never wired one; DATA_CATEGORY existed only to be a RetentionPolicy's
// governing category, per 04-data-model.md's Session 11 note), and no
// column anywhere describes "categories of data subjects" for a purpose.
// Both are added here, nullable (expand-first, per 04-data-model.md's
// migration approach) — existing purposes simply have neither set until a
// Privacy Manager fills them in, which is honestly what "we don't know
// yet" should look like, not a fabricated default.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_purposes', function (Blueprint $table) {
            $table->foreignUuid('data_category_id')->nullable()->after('lawful_basis')
                ->constrained('data_categories')->nullOnDelete();
            $table->text('data_subjects_description')->nullable()->after('data_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('consent_purposes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('data_category_id');
            $table->dropColumn('data_subjects_description');
        });
    }
};
