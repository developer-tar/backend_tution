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
        Schema::create('course_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_content_id')->index();
            $table->foreign('course_content_id', 'course_contents_id_foreign')
            ->references('id')
            ->on('course_contents')
            ->onDelete('cascade');

            $table->foreignId('course_topic_id')->index()->nullable();
            $table->foreign('course_topic_id', 'course_topic_tests_foreign')
            ->references('id')
            ->on('course_topics')
            ->onDelete('cascade');

            $table->foreignId('course_sub_topic_id')->index()->nullable();
            $table->foreign('course_sub_topic_id', 'course_sub_topics_foreign')
            ->references('id')
            ->on('course_sub_topics')
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
        Schema::dropIfExists('course_tests');
    }
};
