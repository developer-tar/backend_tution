<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parent_child', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->index();
            $table->foreign('parent_id', 'parent_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreignId('child_id')->index();
            $table->foreign('child_id', 'child_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
                
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->nullable()->comment('1= Pending, 2 = Approved 3= Rejected');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_childs');
    }
};
