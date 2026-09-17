<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $spain = Country::where('iso_code', 'ES')->firstOrFail();
        $france = Country::where('iso_code', 'FR')->firstOrFail();

        City::create([
            'country_id' => $spain->id,
            'name' => 'Madrid',
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ]);

        City::create([
            'country_id' => $spain->id,
            'name' => 'Barcelona',
            'latitude' => 41.3874,
            'longitude' => 2.1686,
        ]);

        City::create([
            'country_id' => $france->id,
            'name' => 'Paris',
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ]);
    }
}