<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * One tutor can have multiple classes (classrooms) per course.
     */
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('tutor');
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('schedule_summary')->nullable()->comment('e.g. Mon/Wed 10am');
            $table->timestamps();

            $table->index(['course_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classrooms');
    }
};
