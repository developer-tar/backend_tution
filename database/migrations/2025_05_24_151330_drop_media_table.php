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
        Schema::dropIfExists('media');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->tinyInteger('type');
            $table->integer('model_id');
            $table->string('model_type');
            $table->string('folder_name')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
