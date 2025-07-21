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
        Schema::create('course_time_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('course_id')->constrained('courses');
            
            $table->foreignId('academic_course_id')->constrained('acdemic_course'); // ✅ Corrected spelling
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('weekday_id')->constrained('week_days');

            $table->string('start_time'); // format: HH:MM
            $table->string('end_time');
            $table->tinyInteger('seats');

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_time_slots');
    }
};
