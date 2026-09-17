<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\City;
use Illuminate\Database\Seeder;

class AirportSeeder extends Seeder
{
    public function run(): void
    {
        $madrid = City::where('name', 'Madrid')->firstOrFail();
        $barcelona = City::where('name', 'Barcelona')->firstOrFail();

        Airport::create([
            'city_id' => $madrid->id,
            'name' => 'Adolfo Suárez Madrid-Barajas Airport',
            'iata_code' => 'MAD',
            'icao_code' => 'LEMD',
            'latitude' => 40.4936,
            'longitude' => -3.5668,
        ]);

        Airport::create([
            'city_id' => $barcelona->id,
            'name' => 'Barcelona-El Prat Airport',
            'iata_code' => 'BCN',
            'icao_code' => 'LEBL',
            'latitude' => 41.2974,
            'longitude' => 2.0833,
        ]);
    }
}