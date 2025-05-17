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
        Schema::create('course_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->index();
            $table->foreign('course_id', 'subject_course_id_foreign')
            ->references('id')
            ->on('courses')
            ->onDelete('cascade');

            $table->foreignId('subject_id')->index();
            $table->foreign('subject_id', 'subject_user_id_foreign')
            ->references('id')
            ->on('subjects')
            ->onDelete('cascade');
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
        Schema::dropIfExists('course_subject');
    }
};
