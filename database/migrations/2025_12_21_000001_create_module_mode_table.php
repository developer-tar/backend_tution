<?php

use Database\Seeders\ModuleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(config('constants.module_status.active'))->comment('0=Inactive, 1=Active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Run the seeder to populate module table
        Artisan::call('db:seed', [
            '--class' => ModuleSeeder::class,
            '--force' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module');
    }
};
