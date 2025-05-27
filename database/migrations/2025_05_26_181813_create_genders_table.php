<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('genders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->index();
            $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->comment('1= Pending, 2 = Approved 3= Rejected');
            $table->softDeletes();
            $table->timestamps();
        });
        \Artisan::call('db:seed', [
            '--class' => 'GendersTableSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('genders');
    }
};
