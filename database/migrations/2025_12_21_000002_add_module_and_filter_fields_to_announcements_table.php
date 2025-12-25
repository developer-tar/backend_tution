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
        Schema::table('announcements', function (Blueprint $table) {
            // Change status to tinyint using config constants
            $table->tinyInteger('status')->default(config('constants.announcement_status.draft'))->change();

            // Add module foreign key reference to module table
            $table->foreignId('module_id')->nullable()->after('status')
                ->constrained('module')->onDelete('set null');

            // Add academic year reference
            $table->foreignId('academic_year_id')->nullable()->after('module_id')
                ->constrained('acdemic_years')->onDelete('set null');

            // Add course reference (for courses mode)
            $table->foreignId('course_id')->nullable()->after('academic_year_id')
                ->constrained('courses')->onDelete('set null');

            // Add class reference (timeslot)
            $table->foreignId('class_id')->nullable()->after('course_id')
                ->constrained('course_time_slots')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropForeign(['academic_year_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['class_id']);

            $table->dropColumn(['module_id', 'academic_year_id', 'course_id', 'class_id']);

            // Revert status to default
            $table->tinyInteger('status')->default(1)->change();
        });
    }
};
