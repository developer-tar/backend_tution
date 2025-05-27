<?php

namespace Database\Seeders;

use App\Models\Month;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MonthsTableSeeder extends Seeder
{
    public function run(): void
    {
        $months = config('constants.months');

        foreach ($months as  $month) {
            Month::firstOrCreate([
                'name' => $month,
            ]);
         
        }
    }
}
