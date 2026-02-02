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
        Schema::create('course_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('course_price_id')->nullable()->constrained('course_prices')->onDelete('cascade');
            $table->integer('number_of_installments')->default(1);
            $table->decimal('total_course_fee', 8, 2); // Total course fee to be paid in installments
            $table->string('currency', 10)->default('gbp');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_installments');
    }
};
