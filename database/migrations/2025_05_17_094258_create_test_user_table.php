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
        Schema::create('test_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_user_id')->index()->nullable();
            $table->foreign('topic_user_id', 'topic_user_id1_foreign')
            ->references('id')
            ->on('topic_user')
            ->onDelete('cascade');
            
            $table->foreignId('sub_topic_user_id')->index()->nullable();
            $table->foreign('sub_topic_user_id', 'sub_topic_user_id1_foreign')
            ->references('id')
            ->on('sub_topic_user')
            ->onDelete('cascade');
            
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
        Schema::dropIfExists('test_user');
    }
};
