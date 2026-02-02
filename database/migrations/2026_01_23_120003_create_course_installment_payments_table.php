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
        Schema::create('course_installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('course_installment_id')->constrained('course_installments')->onDelete('cascade');
            $table->foreignId('course_installment_item_id')->constrained('course_installment_items')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Buyer/Parent
            $table->foreignId('student_id')->nullable()->constrained('users')->onDelete('cascade'); // Student (if parent buying)
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->integer('installment_number');
            $table->decimal('amount', 8, 2);
            $table->string('currency', 10)->default('gbp');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded', 'overdue'])->default('pending');
            $table->date('due_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_installment_payments');
    }
};
