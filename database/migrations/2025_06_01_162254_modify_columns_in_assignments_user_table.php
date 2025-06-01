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
        Schema::table('assignments_user', function (Blueprint $table) {
            $table->dropForeign('course_user_id_foreign');
            $table->dropColumn('course_user_id');
            $table->foreignId('course_id')->after('id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('buyer_id')->after('course_id')->constrained('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments_user', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign([ 'buyer_id']);
            $table->dropColumn(['course_id', 'buyer_id']);
            $table->foreignId('course_user_id')->index();
            $table->foreign('course_user_id', 'course_user_id_foreign')
                ->references('id')
                ->on('course_user')
                ->onDelete('cascade');

        });
    }
};
