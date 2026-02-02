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
        Schema::create('course_installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_installment_id')->constrained('course_installments')->onDelete('cascade');
            $table->integer('installment_number'); // 1, 2, 3, etc.
            $table->decimal('amount', 8, 2); // Amount for this specific installment
            $table->date('due_date')->nullable(); // Optional due date for this installment
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_installment_items');
    }
};
