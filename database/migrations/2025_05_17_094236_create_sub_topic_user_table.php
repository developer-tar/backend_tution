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
        Schema::create('sub_topic_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_user_id')->index();
            $table->foreign('content_user_id', 'content_user_ids1_foreign')
            ->references('id')
            ->on('content_user')
            ->onDelete('cascade');
            
            $table->foreignId('sub_topic_id')->index();
            $table->foreign('sub_topic_id', 'course_sub_topics_id_foreign')
            ->references('id')
            ->on('course_sub_topics')
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
        Schema::dropIfExists('sub_topic_user');
    }
};
