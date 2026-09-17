<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $madrid = City::where('name', 'Madrid')->firstOrFail();
        $barcelona = City::where('name', 'Barcelona')->firstOrFail();

        Place::create([
            'city_id' => $madrid->id,
            'name' => 'Puerta del Sol',
            'description' => 'Plaza emblemática del centro de Madrid.',
            'latitude' => 40.4169,
            'longitude' => -3.7035,
        ]);

        Place::create([
            'city_id' => $madrid->id,
            'name' => 'Museo del Prado',
            'description' => 'Museo de arte situado en el centro de Madrid.',
            'latitude' => 40.4138,
            'longitude' => -3.6921,
        ]);

        Place::create([
            'city_id' => $barcelona->id,
            'name' => 'Sagrada Familia',
            'description' => 'Basílica diseñada por Antoni Gaudí.',
            'latitude' => 41.4036,
            'longitude' => 2.1744,
        ]);

        Place::create([
            'city_id' => $barcelona->id,
            'name' => 'Park Güell',
            'description' => 'Parque monumental diseñado por Antoni Gaudí.',
            'latitude' => 41.4145,
            'longitude' => 2.1527,
        ]);
    }
}