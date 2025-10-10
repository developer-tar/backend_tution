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
        Schema::create('mock_exam_user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_purchase_id')->constrained('mock_exam_purchases')->onDelete('cascade');
            $table->foreignId('mock_exam_question_id')->constrained('mock_exam_questions')->onDelete('cascade');
            $table->foreignId('mock_exam_option_id')->nullable()->constrained('mock_exam_options')->onDelete('cascade');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_exam_user_answers');
    }
};
