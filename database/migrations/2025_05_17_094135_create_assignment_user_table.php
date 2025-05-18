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
        Schema::create('assignments_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_user_id')->index();
            $table->foreign('course_user_id', 'course_user_id_foreign')
            ->references('id')
            ->on('course_user')
            ->onDelete('cascade');
            $table->foreignId('course_assignment_id')->index();
            $table->foreign('course_assignment_id', 'course_assignment_id_foreign')
            ->references('id')
            ->on('course_assignments')
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
        Schema::dropIfExists('assignments_user');
    }
};
