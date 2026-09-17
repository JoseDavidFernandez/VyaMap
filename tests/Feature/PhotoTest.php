<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_attach_photo_to_another_users_trip(): void
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
            ->postJson('/photos', [
                'trip_id' => $trip->id,
                'path' => 'photos/test.jpg',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_id']);

        $this->assertDatabaseMissing('photos', [
            'trip_id' => $trip->id,
        ]);
    }

    public function test_user_cannot_attach_photo_to_another_users_visit(): void
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

        $visit = Visit::create([
            'user_id' => $user1->id,
            'city_id' => $city->id,
            'visited_from' => '2026-09-10',
            'visited_until' => '2026-09-12',
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson('/photos', [
                'visit_id' => $visit->id,
                'path' => 'photos/test.jpg',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['visit_id']);
    }

    public function test_user_cannot_attach_photo_to_another_users_journal_entry(): void
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

        $entry = JournalEntry::create([
            'journal_day_id' => $day->id,
            'title' => 'Entry',
            'content' => 'Test',
            'city_id' => $city->id,
            'sort_order' => 1,
        ]);

        $response = $this
            ->actingAs($user2)
            ->postJson('/photos', [
                'journal_entry_id' => $entry->id,
                'path' => 'photos/test.jpg',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['journal_entry_id']);
    }
}