<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        ]);

        // Seed parent and student data
        $this->call([
            ParentStudentSeeder::class,
        ]);

        // Create test admin user
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);
    }
}
