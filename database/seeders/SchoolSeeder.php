<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\School;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = config('constants.schools');

        foreach ($schools as $school) {
            // Handle both string and array formats
            $schoolName = is_array($school) ? ($school['name'] ?? null) : $school;
            $countryCode = is_array($school) ? ($school['country_code'] ?? 'GB') : 'GB';

            if (!$schoolName) {
                continue;
            }

            // Get country by code
            $country = Country::where('code', $countryCode)->first();
            if (!$country) {
                // Fallback to UK if country not found
                continue;
            }

            // Check if school already exists by name (case-insensitive)
            $existingSchool = School::whereRaw('LOWER(name) = ?', [strtolower($schoolName)])
                ->first();

            // Skip if school already exists
            if ($existingSchool) {
                // Update country_id if it's missing or different
                if (!$existingSchool->country_id || $existingSchool->country_id !== $country->id) {
                    $existingSchool->update(['country_id' => $country->id]);
                }
                continue;
            }

            // Create school data
            $schoolData = [
                'name' => $schoolName,
                'status' => 'active',
                'country_id' => $country->id,
            ];

            // If school is an array, merge additional fields
            if (is_array($school)) {
                $schoolData = array_merge($schoolData, [
                    'address' => $school['address'] ?? null,
                    'phone' => $school['phone'] ?? null,
                    'email' => $school['email'] ?? null,
                    'logo' => $school['logo'] ?? null,
                    'website' => $school['website'] ?? null,
                    'status' => $school['status'] ?? 'active',
                ]);
            }

            // Create the school
            School::create($schoolData);
        }
    }
}
