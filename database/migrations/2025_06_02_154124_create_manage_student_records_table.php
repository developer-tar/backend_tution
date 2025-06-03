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
        Schema::create('manage_student_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('parent_id')->nullable()->index();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users')->onDelete('cascade');
            $table->morphs('model');
            $table->string('stripe_session_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('amount', 8, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->tinyInteger('is_completed')->default(config('constants.completed.NO'))->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manage_student_records');
    }
};
