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
        Schema::create('paper_extract_user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_purchase_id')->constrained('paper_purchases')->onDelete('cascade');
            $table->foreignId('paper_extract_question_id')->constrained('paper_extract_questions')->onDelete('cascade');
            $table->string('selected_option_key', 10)->nullable()->comment('e.g. A, B or index as string');
            $table->unsignedTinyInteger('is_correct')->default(0);
            $table->timestamps();

            $table->index(['paper_purchase_id', 'paper_extract_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_extract_user_answers');
    }
};
