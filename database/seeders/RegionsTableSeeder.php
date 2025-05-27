<?php

namespace Database\Seeders;

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

        foreach ($regions as  $region) {
            Region::firstOrCreate([
                'name' => $region,
            ]);
         
        }
    }
}
