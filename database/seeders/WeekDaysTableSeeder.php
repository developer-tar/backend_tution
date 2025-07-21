<?php

namespace Database\Seeders;

use App\Models\Day;
use App\Models\WeekDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeekDaysTableSeeder extends Seeder {
    public function run(): void {
        $weekDays = config('constants.week_days');
        foreach($weekDays as $weekDay){

            WeekDay::firstOrCreate([
                'name' => $weekDay,
            ]);
        }
    }
}
