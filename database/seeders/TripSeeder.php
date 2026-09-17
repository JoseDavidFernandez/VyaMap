<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@vyamap.test')->firstOrFail();

        Trip::create([
            'user_id' => $user->id,
            'name' => 'Madrid & Barcelona',
            'description' => 'Viaje de prueba para desarrollar VyaMap.',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);
    }
}