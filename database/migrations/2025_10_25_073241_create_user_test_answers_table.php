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
        Schema::create('user_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('test_id')->constrained('course_tests')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('course_questions')->onDelete('cascade');
            $table->foreignId('selected_option_id')->constrained('course_options')->onDelete('cascade');
            $table->integer('time_taken_seconds');
            $table->boolean('is_correct')->default(false);
            $table->integer('attempt_number')->default(1);
            $table->timestamps();
            
            $table->index(['user_id', 'test_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_test_answers');
    }
};
