<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('paper_extract_questions') && !Schema::hasColumn('paper_extract_questions', 'section')) {
            Schema::table('paper_extract_questions', function (Blueprint $table) {
                $table->string('section')->nullable()->after('question_number')->comment('Section name (e.g., Section A, Part 1)');
                $table->index('section');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('paper_extract_questions') && Schema::hasColumn('paper_extract_questions', 'section')) {
            Schema::table('paper_extract_questions', function (Blueprint $table) {
                $table->dropIndex(['section']);
                $table->dropColumn('section');
            });
        }
    }
};
