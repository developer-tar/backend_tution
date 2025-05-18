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
        Schema::create('course_result_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->index();
            $table->foreign('student_id', 'student_id111_foreign')
            ->references('id')
            ->on('users')
            ->onDelete('cascade');

            $table->foreignId('test_id')->index();
            $table->foreign('test_id', 'test_id111_foreign')
            ->references('id')
            ->on('course_tests')
            ->onDelete('cascade');

            $table->string('test_score');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_result_users');
    }
};
