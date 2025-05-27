<?php

namespace Database\Seeders;

use App\Models\Gender;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GendersTableSeeder extends Seeder {
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $genders = config('constants.genders');

        foreach ($genders as  $gender) {
            Gender::firstOrCreate([
                'name' => $gender,
            ]);
        }
    }
}
