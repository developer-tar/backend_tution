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
        Schema::table('course_result_users', function (Blueprint $table) {
            $table->integer('attempt_count')->default(1)->after('test_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_result_users', function (Blueprint $table) {
            $table->dropColumn('attempt_count');
        });
    }
};
