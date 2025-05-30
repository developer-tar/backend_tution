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
        Schema::create('answer_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_user_id')->index();
            $table->foreign('option_user_id', 'option_users_id1111_foreign')
            ->references('id')
            ->on('option_user')
            ->onDelete('cascade');

            $table->string('taken_time_in_sec');
            $table->tinyInteger('is_completed')->default(config('constants.completed.NO'));
            $table->dateTime('completed_at')->nullable();  
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_user');
    }
};
