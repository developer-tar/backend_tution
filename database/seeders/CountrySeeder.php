<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'name' => 'United Kingdom',
                'code' => 'GB',
                'code_3' => 'GBR',
                'phone_code' => '+44',
                'phone_number_length' => 10,
                'status' => config('constants.statuses.APPROVED'),
            ],
            [
                'name' => 'Dubai',
                'code' => 'DU',
                'code_3' => 'DXB',
                'phone_code' => '+971',
                'phone_number_length' => 9,
                'status' => config('constants.statuses.APPROVED'),
            ],
        ];

        foreach ($countries as $countryData) {
            // Check if country already exists by name (case-insensitive)
            $existingCountry = Country::whereRaw('LOWER(name) = ?', [strtolower($countryData['name'])])
                ->first();

            // Skip if country already exists
            if ($existingCountry) {
                continue;
            }

            // Create the country
            Country::create($countryData);
        }
    }
}
