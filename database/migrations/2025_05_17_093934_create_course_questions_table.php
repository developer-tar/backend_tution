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
        Schema::create('course_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_test_id')->constrained('course_tests')->index();
            $table->string('name');
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->nullable()->comment('1= Pending, 2 = Approved 3= Rejected');
            $table->string('duration_in_sec')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_questions');
    }
};
