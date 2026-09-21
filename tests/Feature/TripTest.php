<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_end_date_cannot_be_before_start_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/trips', [
            'name' => 'Viaje inválido',
            'description' => 'Test de validación',
            'start_date' => '2026-09-12',
            'end_date' => '2026-09-10',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_authenticated_user_can_create_trip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/trips', [
            'name' => 'Viaje a Barcelona',
            'description' => 'Viaje creado desde un test.',
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
        ]);

        $trip = $user->trips()->latest('id')->first();

        $response
            ->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'user_id' => $user->id,
            'name' => 'Viaje a Barcelona',
            'description' => 'Viaje creado desde un test.',
            'start_date' => '2026-09-20',
            'end_date' => '2026-09-22',
        ]);
    }
}