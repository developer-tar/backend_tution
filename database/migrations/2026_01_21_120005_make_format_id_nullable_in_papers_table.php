<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('papers')) {
            // Check if format_id column exists and is not nullable
            $columnInfo = DB::select("
                SELECT IS_NULLABLE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'papers' 
                AND COLUMN_NAME = 'format_id'
            ");

            if (!empty($columnInfo) && $columnInfo[0]->IS_NULLABLE === 'NO') {
                Schema::table('papers', function (Blueprint $table) {
                    $table->foreignId('format_id')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('papers')) {
            // Note: We can't easily make it non-nullable again if there are NULL values
            // This is a one-way migration for safety
            Schema::table('papers', function (Blueprint $table) {
                // Only change if there are no NULL values
                $nullCount = DB::table('papers')->whereNull('format_id')->count();
                if ($nullCount === 0) {
                    $table->foreignId('format_id')->nullable(false)->change();
                }
            });
        }
    }
};
