<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class FlightSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@vyamap.test')->firstOrFail();

        $trip = Trip::where('user_id', $user->id)
            ->where('name', 'Madrid & Barcelona')
            ->firstOrFail();

        $madridAirport = Airport::where('iata_code', 'MAD')->firstOrFail();
        $barcelonaAirport = Airport::where('iata_code', 'BCN')->firstOrFail();

        Flight::create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $madridAirport->id,
            'destination_airport_id' => $barcelonaAirport->id,
            'flight_number' => 'VY1234',
            'departure_at' => '2026-09-10 08:00:00',
            'arrival_at' => '2026-09-10 09:15:00',
            'airline' => 'Vueling',
        ]);
    }
}