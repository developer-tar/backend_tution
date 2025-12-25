<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ParentStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $parentRole = Role::where('name', config('constants.roles.PARENT'))->first();
        $studentRole = Role::where('name', config('constants.roles.STUDENT'))->first();

        if (!$parentRole || !$studentRole) {
            $this->command->error('Parent or Student role not found. Please run RolesSeeder first.');
            return;
        }

        // Get reference data (assuming these exist from other seeders)
        $years = DB::table('years')->pluck('id')->toArray();
        $months = DB::table('months')->pluck('id')->toArray();
        $days = DB::table('days')->pluck('id')->toArray();
        $regions = DB::table('regions')->pluck('id')->toArray();
        $genders = DB::table('genders')->pluck('id')->toArray();
        $targetSchools = DB::table('target_schools')->pluck('id')->toArray();

        if (empty($years) || empty($months) || empty($days) || empty($regions) || empty($genders) || empty($targetSchools)) {
            $this->command->error('Required reference data not found. Please run other seeders first.');
            return;
        }

        DB::beginTransaction();
        try {
            // Create Parent Users
            $parents = [
                [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'john.smith@example.com',
                    'password' => Hash::make('password'),
                ],
                [
                    'first_name' => 'Sarah',
                    'last_name' => 'Johnson',
                    'email' => 'sarah.johnson@example.com',
                    'password' => Hash::make('password'),
                ],
                [
                    'first_name' => 'Michael',
                    'last_name' => 'Williams',
                    'email' => 'michael.williams@example.com',
                    'password' => Hash::make('password'),
                ],
            ];

            foreach ($parents as $parentData) {
                $parent = User::firstOrCreate(
                    ['email' => $parentData['email']],
                    $parentData
                );

                // Attach Parent role if not already attached
                if (!$parent->roles()->where('role_id', $parentRole->id)->exists()) {
                    $parent->roles()->attach($parentRole->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Create Student Users and Student Details
            $students = [
                [
                    'parent_email' => 'john.smith@example.com',
                    'first_name' => 'Emma',
                    'last_name' => 'Smith',
                    'email' => 'emma.smith@example.com',
                    'password' => Hash::make('12345678'),
                    'display_name' => 'EmmaS',
                    'year_id' => $years[array_rand($years)],
                    'month_id' => $months[array_rand($months)],
                    'day_id' => $days[array_rand($days)],
                    'region_id' => $regions[array_rand($regions)],
                    'gender_id' => $genders[array_rand($genders)],
                    'target_school_id' => $targetSchools[array_rand($targetSchools)],
                    'show_answer_after_n_attempts' => 2,
                    'allow_view_examiner_report_for_mocks' => 1,
                    'can_change_password' => 1,
                    'bio' => 'Emma is a dedicated student who loves learning.',
                ],
                [
                    'parent_email' => 'john.smith@example.com',
                    'first_name' => 'James',
                    'last_name' => 'Smith',
                    'email' => 'james.smith@example.com',
                    'password' => Hash::make('12345678'),
                    'display_name' => 'JamesS',
                    'year_id' => $years[array_rand($years)],
                    'month_id' => $months[array_rand($months)],
                    'day_id' => $days[array_rand($days)],
                    'region_id' => $regions[array_rand($regions)],
                    'gender_id' => $genders[array_rand($genders)],
                    'target_school_id' => $targetSchools[array_rand($targetSchools)],
                    'show_answer_after_n_attempts' => 3,
                    'allow_view_examiner_report_for_mocks' => 1,
                    'can_change_password' => 1,
                    'bio' => 'James enjoys mathematics and science.',
                ],
                [
                    'parent_email' => 'sarah.johnson@example.com',
                    'first_name' => 'Olivia',
                    'last_name' => 'Johnson',
                    'email' => 'olivia.johnson@example.com',
                    'password' => Hash::make('12345678'),
                    'display_name' => 'OliviaJ',
                    'year_id' => $years[array_rand($years)],
                    'month_id' => $months[array_rand($months)],
                    'day_id' => $days[array_rand($days)],
                    'region_id' => $regions[array_rand($regions)],
                    'gender_id' => $genders[array_rand($genders)],
                    'target_school_id' => $targetSchools[array_rand($targetSchools)],
                    'show_answer_after_n_attempts' => 1,
                    'allow_view_examiner_report_for_mocks' => 0,
                    'can_change_password' => 1,
                    'bio' => 'Olivia is passionate about reading and writing.',
                ],
                [
                    'parent_email' => 'michael.williams@example.com',
                    'first_name' => 'Noah',
                    'last_name' => 'Williams',
                    'email' => 'noah.williams@example.com',
                    'password' => Hash::make('password123'),
                    'display_name' => 'NoahW',
                    'year_id' => $years[array_rand($years)],
                    'month_id' => $months[array_rand($months)],
                    'day_id' => $days[array_rand($days)],
                    'region_id' => $regions[array_rand($regions)],
                    'gender_id' => $genders[array_rand($genders)],
                    'target_school_id' => $targetSchools[array_rand($targetSchools)],
                    'show_answer_after_n_attempts' => 2,
                    'allow_view_examiner_report_for_mocks' => 1,
                    'can_change_password' => 1,
                    'bio' => 'Noah loves sports and outdoor activities.',
                ],
            ];

            foreach ($students as $studentData) {
                $parent = User::where('email', $studentData['parent_email'])->first();

                if (!$parent) {
                    $this->command->warn("Parent not found for email: {$studentData['parent_email']}");
                    continue;
                }

                // Create student user
                $student = User::firstOrCreate(
                    ['email' => $studentData['email']],
                    [
                        'first_name' => $studentData['first_name'],
                        'last_name' => $studentData['last_name'],
                        'email' => $studentData['email'],
                        'password' => $studentData['password'],
                        'status' => config('constants.statuses.APPROVED'),
                    ]
                );

                // Attach Student role if not already attached
                if (!$student->roles()->where('role_id', $studentRole->id)->exists()) {
                    $student->roles()->attach($studentRole->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create student detail
                StudentDetail::firstOrCreate(
                    ['child_id' => $student->id],
                    [
                        'parent_id' => $parent->id,
                        'child_id' => $student->id,
                        'year_id' => $studentData['year_id'],
                        'month_id' => $studentData['month_id'],
                        'day_id' => $studentData['day_id'],
                        'region_id' => $studentData['region_id'],
                        'gender_id' => $studentData['gender_id'],
                        'target_school_id' => $studentData['target_school_id'],
                        'display_name' => $studentData['display_name'],
                        'show_answer_after_n_attempts' => $studentData['show_answer_after_n_attempts'],
                        'allow_view_examiner_report_for_mocks' => $studentData['allow_view_examiner_report_for_mocks'],
                        'can_change_password' => $studentData['can_change_password'],
                        'bio' => $studentData['bio'],
                    ]
                );
            }

            DB::commit();
            $this->command->info('Parent and Student seed data created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding parent and student data: ' . $e->getMessage());
            throw $e;
        }
    }
}
