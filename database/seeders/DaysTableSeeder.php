<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DaysTableSeeder extends Seeder
{
    public function run(): void
    {
        for ($day = 1; $day <= 31; $day++) {
            DB::table('days')->insert([
                'day' => $day,
            ]);
        }
    }
}
