<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_topics', function (Blueprint $table) {
            // First, drop the foreign key constraint using its name
            $table->dropForeign('course_contents_foreign');

            // Then drop the column
            $table->dropColumn('course_content_id');
            $table->foreignId('course_assignment_id')->constrained('course_assignments');
        });
    }

    public function down(): void
    {
        Schema::table('course_topics', function (Blueprint $table) {
            // Re-add the column
            $table->foreignId('course_content_id')->index();

            // Re-add the foreign key constraint
            $table->foreign('course_content_id', 'course_contents_foreign')
                ->references('id')
                ->on('course_contents')
                ->onDelete('cascade');
        });
    }
};
