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
        Schema::create('paper_request_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_paper_to_home_id')->constrained('requested_papers_to_home')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('Parent who made the request');
            $table->foreignId('paper_id')->constrained('papers')->onDelete('cascade');
            $table->foreignId('billing_information_id')->constrained('billing_informations')->onDelete('cascade');
            $table->string('action')->comment('created, updated, deleted, restored');
            $table->text('description')->nullable()->comment('Human-readable description of the action');
            $table->json('old_values')->nullable()->comment('Previous values before update (JSON format)');
            $table->json('new_values')->nullable()->comment('New values after update (JSON format)');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('requested_paper_to_home_id');
            $table->index('user_id');
            $table->index('paper_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paper_request_activity_logs');
    }
};
