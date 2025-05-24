<?php

use App\Models\Week;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\AcdemicYear;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->index();
            $table->foreign('academic_year_id', 'week_academic_mode_user_foreign')
                ->references('id')
              
                ->on('acdemic_years')
                ->onDelete('cascade');


            $table->string('week_number');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->softDeletes();
            $table->timestamps();
        });

        $academicYears = AcdemicYear::all();

        foreach ($academicYears as $year) {
            $startDate = Carbon::create($year->start_year, 1, 1)->startOfWeek(Carbon::MONDAY);
            $endDate = Carbon::create($year->start_year, 12, 31)->endOfWeek(Carbon::SUNDAY);

            $weekNum = 1;

            while ($startDate->lte($endDate)) {
                Week::create([
                    'academic_year_id' => $year->id,
                    'week_number' => 'week ' . $weekNum,
                    'start_date' => $startDate->copy(),
                    'end_date' => $startDate->copy()->endOfWeek(Carbon::SUNDAY)->setTime(22, 0, 0),
                ]);

                $startDate->addWeek();
                $weekNum++;
            }
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weeks');
    }
};
