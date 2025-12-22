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
        // Check if table exists and column doesn't already exist
        if (Schema::hasTable('course_prices') && !Schema::hasColumn('course_prices', 'deleted_at')) {
            Schema::table('course_prices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if table exists and column exists before dropping
        if (Schema::hasTable('course_prices') && Schema::hasColumn('course_prices', 'deleted_at')) {
            Schema::table('course_prices', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
