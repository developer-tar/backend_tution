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
        Schema::create('paper_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->constrained('papers')->onDelete('cascade');
            $table->text('question_text');
            $table->integer('marks')->default(1);
            $table->integer('duration_in_sec')->nullable();
            $table->integer('order')->default(0);
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_questions');
    }
};







