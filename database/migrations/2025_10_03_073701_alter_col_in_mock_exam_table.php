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
        Schema::table('mock_exams', function (Blueprint $table) {
       
            $table->dropColumn('format');

            $table->foreignId('format_id')
                ->after('category_id')
                ->constrained('formats')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mock_exams', function (Blueprint $table) {
           
            $table->dropForeign(['format_id']);
            $table->dropColumn('format_id');

            $table->string('format')->after('category_id');
        });
    }
};
