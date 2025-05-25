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
        Schema::table('course_tests', function (Blueprint $table) {
            $table->dropForeign('course_contents_id_foreign');
            $table->dropColumn('course_content_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_tests', function (Blueprint $table) {
            $table->foreignId('course_content_id')->index();
            $table->foreign('course_content_id', 'course_contents_id_foreign')
                ->references('id')
                ->on('course_contents')
                ->onDelete('cascade');
        });
    }
};
