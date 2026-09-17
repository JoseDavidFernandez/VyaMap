<?php

namespace Database\Seeders;

use App\Models\Journal;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@vyamap.test')->firstOrFail();

        $trip = Trip::where('user_id', $user->id)
            ->where('name', 'Madrid & Barcelona')
            ->firstOrFail();

        Journal::create([
            'trip_id' => $trip->id,
            'title' => 'Madrid & Barcelona',
            'intro' => 'Un pequeño diario de nuestro viaje por Madrid y Barcelona.',
        ]);
    }
}