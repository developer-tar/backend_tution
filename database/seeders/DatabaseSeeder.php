<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles first (required for parent/student seeders)
        $this->call([
            RolesSeeder::class,
        ]);

        // Seed reference data (required for parent/student seeders)
        $this->call([
            YearsTableSeeder::class,
            MonthsTableSeeder::class,
            DaysTableSeeder::class,
            RegionsTableSeeder::class,
            GendersTableSeeder::class,
            TargetSchoolsSeeder::class,
            SubjectSeeder::class,
        ]);

        // Seed parent and student data
        $this->call([
            ParentStudentSeeder::class,
        ]);

        // Seed tutors (for tutor panel login)
        $this->call([
            TutorSeeder::class,
        ]);

        // Create test admin user (idempotent)
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'password' => Hash::make('password'),
            ]
        );
    }
}
