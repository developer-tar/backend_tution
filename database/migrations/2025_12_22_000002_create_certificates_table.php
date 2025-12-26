<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_id')->constrained('awards')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->string('certificate_number')->unique(); // Unique certificate number
            $table->date('issued_date');
            $table->text('achievement_details')->nullable(); // Additional details about the achievement
            $table->string('pdf_path')->nullable(); // Path to generated PDF certificate
            $table->tinyInteger('status')->default(1)->comment('0=Revoked, 1=Active');
            $table->softDeletes();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('student_id');
            $table->index('award_id');
            $table->index('certificate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
