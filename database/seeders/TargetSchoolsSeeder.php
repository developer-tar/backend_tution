<?php

namespace Database\Seeders;

use App\Models\TargetSchool;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TargetSchoolsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $targetSchools = config('constants.target_schools');

        foreach ($targetSchools as  $targetSchool) {
            TargetSchool::firstOrCreate([
                'name' => $targetSchool,
            ]);
         
        }
    }
}
