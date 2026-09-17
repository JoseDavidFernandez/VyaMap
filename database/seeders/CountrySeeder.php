<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        Country::create([
            'name' => 'France',
            'iso_code' => 'FR',
        ]);
    }
}