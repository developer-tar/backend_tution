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
        Schema::table('paper_extracts', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('description')->constrained('mock_exam_categories')->onDelete('set null');
            $table->foreignId('format_id')->nullable()->after('category_id')->constrained('formats')->onDelete('set null');
            $table->decimal('price', 10, 2)->nullable()->after('format_id');
            $table->string('currency', 10)->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paper_extracts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['format_id']);
            $table->dropColumn(['category_id', 'format_id', 'price', 'currency']);
        });
    }
};
