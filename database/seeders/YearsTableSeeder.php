<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class YearsTableSeeder extends Seeder
{
    public function run(): void
    {
        $years = range(1900, 2025);

        foreach ($years as $year) {
            DB::table('years')->insert([
                'year' => $year,
            ]);
        }
    }
}
