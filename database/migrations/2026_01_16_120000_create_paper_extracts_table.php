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
        Schema::create('paper_extracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->nullable()->constrained('papers')->onDelete('set null');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('source_file_path')->nullable()->comment('Path to the original PDF/document');
            $table->string('extracted_file_path')->nullable()->comment('Path to processed/extracted data file');
            $table->enum('extraction_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('extraction_error')->nullable();
            $table->json('metadata')->nullable()->comment('Additional metadata about the extraction');
            $table->integer('total_questions')->default(0);
            $table->integer('total_pages')->nullable();
            $table->string('subject')->nullable();
            $table->string('exam_board')->nullable();
            $table->string('year')->nullable();
            $table->string('level')->nullable()->comment('e.g., GCSE, A-Level, 11 Plus');
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'));
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->softDeletes();
            $table->timestamps();

            $table->index('paper_id');
            $table->index('extraction_status');
            $table->index('status');
            $table->index(['subject', 'year', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_extracts');
    }
};
