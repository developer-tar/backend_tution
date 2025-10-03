<?php

namespace Database\Seeders;

use App\Models\Format;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = config('constants.formats');

        foreach ($formats as $format) {
            Format::firstOrCreate([
                'name' => $format,
            ]);
        }
    }
}
