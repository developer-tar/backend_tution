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
        Schema::create('mock_exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('mock_exam_categories')->onDelete('cascade');
            $table->string('format'); // online, download, physical, any
            $table->decimal('price', 8, 2)->default(0);
            $table->string('currency')->default('€');
            $table->integer('duration_minutes')->nullable();
            $table->integer('total_marks')->nullable();
            $table->foreignId('school_id')->nullable()->constrained('schools')->onDelete('set null');
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
        Schema::dropIfExists('mock_exams');
    }
};
