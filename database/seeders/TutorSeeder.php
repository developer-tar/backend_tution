<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TutorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tutorRole = Role::where('name', config('constants.roles.TUTOR'))->first();

        if (!$tutorRole) {
            $this->command->error('Tutor role not found. Please run RolesSeeder first.');
            return;
        }

        $tutors = [
            [
                'first_name' => 'James',
                'last_name' => 'Wilson',
                'email' => 'james.wilson@tutor.com',
                'password' => Hash::make('password'),
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Brown',
                'email' => 'emily.brown@tutor.com',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($tutors as $data) {
            $tutor = User::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            if (!$tutor->roles()->where('role_id', $tutorRole->id)->exists()) {
                $tutor->roles()->attach($tutorRole->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
