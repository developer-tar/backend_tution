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
        Schema::create('requested_papers_to_home', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('paper_id')->constrained('papers')->onDelete('cascade');
            $table->foreignId('billing_information_id')->constrained('billing_informations')->onDelete('cascade');
            $table->boolean('requested')->default(1);
            $table->softDeletes();
            $table->timestamps();
            
            // Unique composite index to prevent duplicate requests
            $table->unique(['parent_id', 'paper_id']);
            $table->index('parent_id');
            $table->index('paper_id');
            $table->index('billing_information_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_papers_to_home');
    }
};
