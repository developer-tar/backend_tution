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
        Schema::create('paper_user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_purchase_id')->constrained('paper_purchases')->onDelete('cascade');
            $table->foreignId('paper_question_id')->constrained('paper_questions')->onDelete('cascade');
            $table->foreignId('paper_option_id')->nullable()->constrained('paper_options')->onDelete('cascade');
            $table->boolean('is_correct')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_user_answers');
    }
};







