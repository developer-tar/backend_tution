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
        Schema::create('option_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_user_id')->index();
            $table->foreign('question_user_id', 'question_user_id1111_foreign')
            ->references('id')
            ->on('question_user')
            ->onDelete('cascade');
           
            $table->dateTime('completed_at')->nullable();  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_users');
    }
};
