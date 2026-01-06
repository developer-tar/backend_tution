<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modes = [
            [
                'name' => 'Courses',
                'description' => 'Announcements for course-related content',
                'status' => config('constants.module_status.active'),
            ],
            [
                'name' => 'Papers',
                'description' => 'Announcements for paper-related content',
                'status' => config('constants.module_status.active'),
            ],
            [
                'name' => 'Mock Exams',
                'description' => 'Announcements for mock exam-related content',
                'status' => config('constants.module_status.active'),
            ],
        ];

        foreach ($modes as $mode) {
            Module::firstOrCreate(
                ['name' => $mode['name']],
                $mode
            );
        }
    }
}
