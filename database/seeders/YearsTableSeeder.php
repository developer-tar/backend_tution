<?php

namespace Database\Seeders;

use App\Models\Year;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class YearsTableSeeder extends Seeder
{
    public function run(): void
    {
        $years = range(1997, 2025);

        foreach ($years as $year) {
          Year::firstOrCreate(['name' => $year]);

        }
    }
}
