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
        Schema::create('course_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->index();
            $table->foreign('assignment_id', 'course_assignments_foreign')
            ->references('id')
            ->on('course_assignments')
            ->onDelete('cascade');
            $table->foreignId('course_subject_id')->index();
            $table->foreign('course_subject_id', 'course_subject_foreign')
            ->references('id')
            ->on('course_subject')
            ->onDelete('cascade');
            $table->string('name');
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->nullable()->comment('1= Pending, 2 = Approved 3= Rejected');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_contents');
    }
};
