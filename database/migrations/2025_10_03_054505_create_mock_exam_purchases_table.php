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
        Schema::create('mock_exam_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('mock_exam_id')->constrained('mock_exams')->onDelete('cascade');
            $table->string('stripe_session_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('currency')->default('€');
            $table->dateTime('purchased_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->integer('total_marks')->nullable();
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
        Schema::dropIfExists('mock_exam_purchases');
    }
};
