<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_entry_in_another_users_journal(): void
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

        $journal = Journal::create([
            'trip_id' => $trip->id,
            'title' => 'User 1 Journal',
        ]);

        $day = JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 1,
            'date' => '2026-09-10',
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson('/journal-entries', [
                'journal_day_id' => $day->id,
                'title' => 'Unauthorized Entry',
                'content' => 'Test',
                'city_id' => $city->id,
                'sort_order' => 1,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['journal_day_id']);
    }

    public function test_place_must_belong_to_the_selected_city(): void
    {
        $user = User::factory()->create();

        $country = Country::create([
            'name' => 'Spain',
            'iso_code' => 'ES',
        ]);

        $madrid = City::create([
            'country_id' => $country->id,
            'name' => 'Madrid',
        ]);

        $barcelona = City::create([
            'country_id' => $country->id,
            'name' => 'Barcelona',
        ]);

        $place = Place::create([
            'city_id' => $barcelona->id,
            'name' => 'Sagrada Familia',
        ]);

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Test Trip',
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
        ]);

        $journal = Journal::create([
            'trip_id' => $trip->id,
            'title' => 'Test Journal',
        ]);

        $day = JournalDay::create([
            'journal_id' => $journal->id,
            'day_number' => 1,
            'date' => '2026-09-10',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/journal-entries', [
                'journal_day_id' => $day->id,
                'title' => 'Invalid Place',
                'content' => 'Test',
                'city_id' => $madrid->id,
                'place_id' => $place->id,
                'sort_order' => 1,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['place_id']);
    }
}