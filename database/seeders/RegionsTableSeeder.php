<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RegionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $regions = config('constants.regions');

        foreach ($regions as $regionData) {
            // Handle both array and string formats for backward compatibility
            $regionName = is_array($regionData) ? $regionData['name'] : $regionData;
            $countryCode = is_array($regionData) ? ($regionData['country_code'] ?? 'GB') : 'GB';

            // Get country by code
            $country = Country::where('code', $countryCode)
                ->first();

            if (!$country) {
                // Skip if country not found
                continue;
            }

            $regionModel = Region::firstOrCreate(
                ['name' => $regionName],
                [
                    'country_id' => $country->id,
                    'status' => config('constants.statuses.APPROVED'),
                ]
            );

            // Update country_id if region already existed without it
            if (!$regionModel->country_id) {
                $regionModel->update(['country_id' => $country->id]);
            }
        }
    }
}
