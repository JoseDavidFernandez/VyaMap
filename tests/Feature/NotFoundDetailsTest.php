<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un recurso que no existe es un 404 sin nombres internos: ni la clase del
 * modelo ni el id («No query results for model [App\Models\Trip] 1»), que
 * Laravel incluye en las respuestas JSON aunque APP_DEBUG=false
 * (App\Exceptions\Handler).
 */
class NotFoundDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_model_does_not_reveal_internal_names_in_production(): void
    {
        config(['app.debug' => false]);
        $user = User::factory()->create();

        foreach (['getJson', 'postJson'] as $method) {
            $response = $this->actingAs($user)->{$method}($method === 'getJson' ? '/trips/999999' : '/trips/999999/flights/attach', ['flight_ids' => [1]]);

            $response->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        }

        $html = $this->actingAs($user)->get('/trips/999999')->assertNotFound()->getContent();
        $this->assertStringNotContainsString('App\\Models', $html);
        $this->assertStringNotContainsString('No query results', $html);
    }

    public function test_existing_trips_of_other_users_still_answer_403(): void
    {
        $trip = Trip::create(['user_id' => User::factory()->create()->id, 'name' => 'Other', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);

        $this->actingAs(User::factory()->create())->getJson("/trips/{$trip->id}")->assertForbidden();
    }
}
