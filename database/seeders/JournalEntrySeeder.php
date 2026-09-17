<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Place;
use Illuminate\Database\Seeder;

class JournalEntrySeeder extends Seeder
{
    public function run(): void
    {
        $day1 = JournalDay::where('day_number', 1)->firstOrFail();
        $day2 = JournalDay::where('day_number', 2)->firstOrFail();

        $madrid = City::where('name', 'Madrid')->firstOrFail();
        $barcelona = City::where('name', 'Barcelona')->firstOrFail();

        $sol = Place::where('name', 'Puerta del Sol')->firstOrFail();
        $sagradaFamilia = Place::where('name', 'Sagrada Familia')->firstOrFail();

        JournalEntry::create([
            'journal_day_id' => $day1->id,
            'title' => 'Llegada a Madrid',
            'content' => 'Llegamos a Madrid y comenzamos el viaje visitando el centro de la ciudad.',
            'city_id' => $madrid->id,
            'place_id' => $sol->id,
            'sort_order' => 1,
        ]);

        JournalEntry::create([
            'journal_day_id' => $day1->id,
            'title' => 'Paseo por el centro',
            'content' => 'Después de llegar, recorrimos algunas de las zonas más conocidas del centro.',
            'city_id' => $madrid->id,
            'place_id' => null,
            'sort_order' => 2,
        ]);

        JournalEntry::create([
            'journal_day_id' => $day2->id,
            'title' => 'Descubriendo Barcelona',
            'content' => 'Primer día explorando Barcelona y sus principales lugares de interés.',
            'city_id' => $barcelona->id,
            'place_id' => $sagradaFamilia->id,
            'sort_order' => 1,
        ]);
    }
}