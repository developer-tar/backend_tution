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
        Schema::table('course_assignments', function (Blueprint $table) {
            // Drop existing datetime columns
            $table->dropColumn(['start_date_time', 'end_date_time']);

            // Add new foreign key to weeks table
            $table->foreignId('week_id')
                ->after('id') // optional: specify column order
                ->constrained('weeks')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_assignments', function (Blueprint $table) {
            // Drop the week_id foreign key and column
            $table->dropForeign(['week_id']);
            $table->dropColumn('week_id');

            // Restore the original datetime columns
            $table->dateTime('start_date_time')->nullable();
            $table->dateTime('end_date_time')->nullable();
        });
    }
};
