<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CountrySeeder::class,
            CitySeeder::class,
            AirportSeeder::class,
            PlaceSeeder::class,
            TripSeeder::class,
            VisitSeeder::class,
            FlightSeeder::class,
            JournalSeeder::class,
            JournalDaySeeder::class,
            JournalEntrySeeder::class,
        ]);
    }
}