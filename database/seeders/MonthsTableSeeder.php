<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MonthsTableSeeder extends Seeder
{
    public function run(): void
    {
        $months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        foreach ($months as $index => $month) {
            DB::table('months')->insert([
                'name' => $month,
                'number' => $index + 1, // 1 for January, 12 for December
            ]);
        }
    }
}
