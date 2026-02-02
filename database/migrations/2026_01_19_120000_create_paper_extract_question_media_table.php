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
        Schema::create('paper_extract_question_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_question_id')->constrained('mock_exam_questions')->onDelete('cascade');
            $table->string('media_type')->comment('image, chart, diagram, etc.');
            $table->string('file_path')->comment('Path to stored image file');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('page_number')->nullable()->comment('Page number where media appears');
            $table->integer('position')->default(0)->comment('Position order on the page');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('mock_exam_question_id');
            $table->index('page_number');
            $table->index('media_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_extract_question_media');
    }
};

