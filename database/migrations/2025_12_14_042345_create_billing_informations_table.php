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
        Schema::create('billing_informations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade');
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_informations');
    }
};
