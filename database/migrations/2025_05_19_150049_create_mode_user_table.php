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
        Schema::create('mode_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->index();
            $table->foreign('course_id', 'course_id_mode_user_foreign')
                ->references('id')
                ->on('courses')
                ->onDelete('cascade');

            $table->foreignId('mode_id')->index();
            $table->foreign('mode_id', 'mode_id_mode_user_foreign')
                ->references('id')
                ->on('modes')
                ->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mode_user');
    }
};
