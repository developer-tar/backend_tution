<?php

namespace Database\Seeders;

use App\Models\Day;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DaysTableSeeder extends Seeder {
    public function run(): void {
        for ($day = 1; $day <= 31; $day++) {

            Day::firstOrCreate([
                'name' => sprintf('%02d', $day),
            ]);
        }
    }
}
