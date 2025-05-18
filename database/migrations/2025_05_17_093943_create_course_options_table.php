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
        Schema::create('course_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_question_id')->index();
            $table->foreign('course_question_id', 'course_options_foreign')
            ->references('id')
            ->on('course_questions')
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
        Schema::dropIfExists('course_options');
    }
};
