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
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->index();
            $table->foreign('course_id', 'courses_foreign')
            ->references('id')
            ->on('courses')
            ->onDelete('cascade');
            
            $table->foreignId('buyer_id')->index();
            $table->foreign('buyer_id', 'buyer_foreign')
            ->references('id')
            ->on('users')
            ->onDelete('cascade');

            $table->string('stripe_session_id')->nullable(); 
            $table->string('transaction_id')->nullable();    
            $table->decimal('amount', 8, 2)->nullable();     
            $table->string('currency', 10)->default('gbp');
            $table->tinyInteger('status')->nullable();
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
        Schema::dropIfExists('course_user');
    }
};
