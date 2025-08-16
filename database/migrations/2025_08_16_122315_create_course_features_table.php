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
        Schema::create('course_mode_features', function (Blueprint $table) {
            $table->id();
            $table->text('online_features_names')->nullable();
            $table->text('in_person_features_names')->nullable();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_features');
    }
};
