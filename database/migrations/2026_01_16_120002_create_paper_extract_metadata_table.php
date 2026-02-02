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
        Schema::create('paper_extract_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_extract_id')->constrained('paper_extracts')->onDelete('cascade');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('data_type')->default('string')->comment('string, integer, boolean, json');
            $table->timestamps();

            $table->unique(['paper_extract_id', 'key']);
            $table->index('key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_extract_metadata');
    }
};

