<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_visit_using_another_users_trip(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson("/trips/{$trip->id}/visits", [
                'city_id' => $city->id,
                'visited_from' => '2026-09-10',
                'visited_until' => '2026-09-12',
                'notes' => 'Intento no autorizado',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('visits', [
            'trip_id' => $trip->id,
            'city_id' => $city->id,
        ]);
    }

    public function test_user_can_create_visit_using_their_own_trip(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $city = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Madrid Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson("/trips/{$trip->id}/visits", [
                'city_id' => $city->id,
                'visited_from' => '2026-09-10',
                'visited_until' => '2026-09-12',
                'notes' => 'Primera visita a Madrid',
            ]);

        $response
            ->assertRedirect(route('trips.show', $trip));

        $this->assertDatabaseHas('visits', [
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'city_id' => $city->id,
            'visited_from' => '2026-09-10 00:00:00',
            'visited_until' => '2026-09-12 00:00:00',
            'notes' => 'Primera visita a Madrid',
        ]);
    }
}