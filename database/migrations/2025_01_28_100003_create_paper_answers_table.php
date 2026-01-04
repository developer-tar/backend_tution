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
        Schema::create('paper_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_option_id')->constrained('paper_options')->onDelete('cascade');
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_answers');
    }
};







