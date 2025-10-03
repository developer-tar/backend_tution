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
        Schema::table('mock_exam_purchases', function (Blueprint $table) {
            $table->foreignId('student_id')
                ->after('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade')
                ->comment('For parent purchases - which student this is for');
                
            $table->enum('purchased_by', ['parent', 'student'])
                ->after('status')
                ->default('student')
                ->comment('Who made the purchase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mock_exam_purchases', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn(['student_id', 'purchased_by']);
        });
    }
};
