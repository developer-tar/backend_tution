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
        Schema::create('paper_extract_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_extract_id')->constrained('paper_extracts')->onDelete('cascade');
            $table->integer('question_number')->comment('Question number in the paper');
            $table->text('question_text')->comment('Full question text');
            $table->text('question_text_clean')->nullable()->comment('Cleaned/formatted question text');
            $table->string('question_type')->nullable()->comment('e.g., multiple_choice, short_answer, essay, calculation');
            $table->integer('marks')->default(1);
            $table->integer('page_number')->nullable()->comment('Page number where question appears');
            $table->json('options')->nullable()->comment('For multiple choice questions');
            $table->string('correct_answer')->nullable();
            $table->text('answer_explanation')->nullable();
            $table->string('topic')->nullable();
            $table->string('subtopic')->nullable();
            $table->json('keywords')->nullable()->comment('Extracted keywords for searchability');
            $table->json('metadata')->nullable()->comment('Additional question metadata');
            $table->integer('order')->default(0);
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'));
            $table->softDeletes();
            $table->timestamps();

            $table->index('paper_extract_id');
            $table->index('question_number');
            $table->index('question_type');
            $table->index('topic');
            $table->index('status');
            // Full-text index - uncomment if your MySQL version supports it (MySQL 5.6+)
            // $table->fullText(['question_text', 'question_text_clean']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_extract_questions');
    }
};
