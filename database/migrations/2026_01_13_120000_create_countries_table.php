<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('code', 2)->nullable()->unique()->comment('ISO 3166-1 alpha-2 country code');
                $table->string('code_3', 3)->nullable()->unique()->comment('ISO 3166-1 alpha-3 country code');
                $table->tinyInteger('status')->default(config('constants.statuses.APPROVED'))->comment('1= Pending, 2 = Approved 3= Rejected');
                $table->softDeletes();
                $table->timestamps();

                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
