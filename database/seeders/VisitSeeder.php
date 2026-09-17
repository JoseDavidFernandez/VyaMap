<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;

class VisitSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@vyamap.test')->firstOrFail();

        $trip = Trip::where('user_id', $user->id)
            ->where('name', 'Madrid & Barcelona')
            ->firstOrFail();

        $madrid = City::where('name', 'Madrid')->firstOrFail();
        $barcelona = City::where('name', 'Barcelona')->firstOrFail();

        Visit::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'city_id' => $madrid->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-10',
            'notes' => 'Llegada y visita de Madrid.',
        ]);

        Visit::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'city_id' => $barcelona->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-12',
            'notes' => 'Estancia y visita de Barcelona.',
        ]);
    }
}