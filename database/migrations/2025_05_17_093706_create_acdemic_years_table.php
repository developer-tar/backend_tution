<?php

use App\Models\AcdemicYear;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('acdemic_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('start_year');
            $table->unsignedSmallInteger('end_year');
            $table->softDeletes();
            $table->timestamps();
        });
        $endYear = config('constants.acdemic_end_year');
        foreach (config('constants.acdemic_start_year') as $key => $item) {
            AcdemicYear::create([
                'start_year' => $item,
                'end_year' => $endYear[$key]
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acdemic_years');
    }
};
