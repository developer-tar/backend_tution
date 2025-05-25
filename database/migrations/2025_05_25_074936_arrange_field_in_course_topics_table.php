<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_topics', function (Blueprint $table) {
            $table->dropForeign(['course_assignment_id']);
            $table->dropColumn('course_assignment_id');
            $table->foreignId('course_assignment_id')
                ->after('id')
                ->constrained('course_assignments');
            $table->foreignId('subject_id')
                ->after('course_assignment_id')
                ->constrained('subjects');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_topics', function (Blueprint $table) {
            $table->dropForeign(['course_assignment_id','subject_id']);
            $table->dropColumn('course_assignment_id', 'subject_id');
        });
    }
};
