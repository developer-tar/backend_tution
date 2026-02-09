<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = config('constants.subjects', []);

        foreach ($subjects as $name) {
            Subject::firstOrCreate(
                ['name' => $name],
                ['status' => config('constants.statuses.APPROVED')]
            );
        }
    }
}
