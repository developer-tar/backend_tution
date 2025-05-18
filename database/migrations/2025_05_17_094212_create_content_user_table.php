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
        Schema::create('content_user', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('assignments_user_id')->index();
            $table->foreign('assignments_user_id', 'assignments_user_ids12_foreign')
            ->references('id')
            ->on('assignments_user')
            ->onDelete('cascade');
            $table->foreignId('course_content_id')->index();
            $table->foreign('course_content_id', 'course_contents_ids_foreign')
            ->references('id')
            ->on('course_contents')
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
        Schema::dropIfExists('content_user');
    }
};
