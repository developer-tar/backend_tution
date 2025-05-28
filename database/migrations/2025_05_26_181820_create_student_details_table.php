<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('child_id')
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('Child ID from users table');
            $table->foreignId('year_id')->constrained('years')->onDelete('cascade');
            $table->foreignId('month_id')->constrained('months')->onDelete('cascade');
            $table->foreignId('day_id')->constrained('days')->onDelete('cascade');
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->foreignId('gender_id')->constrained('genders')->onDelete('cascade');
            $table->foreignId('target_school_id')->constrained('target_schools')->onDelete('cascade');
            $table->string('display_name', 100)->unique();
            $table->tinyInteger('show_answer_after_n_attempts')->nullable();
            $table->tinyInteger('allow_view_examiner_report_for_mocks')->nullable();
            $table->tinyInteger('can_change_password')->nullable();
            $table->text('bio')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_details');
    }
};
