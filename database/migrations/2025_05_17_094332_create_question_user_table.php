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
        Schema::create('question_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_user_id')->index()->nullable();
            $table->foreign('test_user_id', 'test_user_id112_foreign')
            ->references('id')
            ->on('test_user')
            ->onDelete('cascade');

            $table->tinyInteger('is_completed')->default(config('constants.completed.NO'))->nullable();  
            $table->dateTime('completed_at')->nullable();  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_user');
    }
};
