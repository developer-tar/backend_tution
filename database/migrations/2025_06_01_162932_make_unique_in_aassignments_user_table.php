<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments_user', function (Blueprint $table) {
            $table->unique(['course_assignment_id', 'buyer_id', 'course_id'], 'assignment_buyer_course_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assignments_user', function (Blueprint $table) {
            $table->dropUnique('assignment_buyer_course_unique');
        });
    }
};
