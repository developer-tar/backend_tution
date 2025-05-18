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
        Schema::create('topic_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_user_id')->index();
            $table->foreign('content_user_id', 'content_user_ids_foreign')
            ->references('id')
            ->on('content_user')
            ->onDelete('cascade');

            $table->foreignId('topic_id')->index();
            $table->foreign('topic_id', 'course_topics_ids_foreign')
            ->references('id')
            ->on('course_topics')
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
        Schema::dropIfExists('topic_user');
    }
};
