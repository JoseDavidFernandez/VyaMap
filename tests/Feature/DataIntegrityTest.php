<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\GoogleMapList;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Models\Airport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    public function test_countries_page_counts_trips_and_cities_per_country_for_the_user_only(): void
    {
        [$spain, $madrid, $seville] = $this->countryWithCities('Spain', 'ES', ['Madrid', 'Seville']);
        [$france, $paris] = $this->countryWithCities('France', 'FR', ['Paris']);

        $t1 = $this->trip($this->user, '2026-01-01', '2026-01-10');
        $t2 = $this->trip($this->user, '2026-02-01', '2026-02-10');
        $this->visit($t1, $madrid, '2026-01-01');
        $this->visit($t1, $seville, '2026-01-02');
        $this->visit($t1, $paris, '2026-01-03');
        $this->visit($t2, $madrid, '2026-02-01');

        // Datos de otro usuario en los mismos países: no cuentan.
        $other = $this->trip(User::factory()->create(), '2026-03-01', '2026-03-02');
        $this->visit($other, $paris, '2026-03-01');

        $this->actingAs($this->user)
            ->get('/countries')
            ->assertInertia(fn ($page) => $page
                ->component('Countries/Index')
                ->has('countries', 2)
                ->where('countries.0.iso_code', 'FR')
                ->where('countries.0.cities_count', 1)
                ->where('countries.0.trips_count', 1)
                ->where('countries.1.iso_code', 'ES')
                ->where('countries.1.cities_count', 2)
                ->where('countries.1.trips_count', 2)
            );
    }

    public function test_countries_page_query_count_does_not_grow_with_the_number_of_countries(): void
    {
        $trip = $this->trip($this->user, '2026-01-01', '2026-01-31');
        [, $city] = $this->countryWithCities('Spain', 'ES', ['Madrid']);
        $this->visit($trip, $city, '2026-01-01');

        $few = $this->countQueries(fn () => $this->actingAs($this->user)->get('/countries')->assertOk());

        foreach (['FR', 'IT', 'PT', 'DE', 'NL', 'BE'] as $i => $iso) {
            [, $c] = $this->countryWithCities("Country {$iso}", $iso, ["City {$iso}"]);
            $this->visit($trip, $c, sprintf('2026-01-%02d', $i + 2));
        }

        $many = $this->countQueries(fn () => $this->actingAs($this->user)->get('/countries')->assertOk());

        $this->assertSame($few, $many, 'El número de consultas no debe depender del número de países');
    }

    public function test_next_trip_uses_the_users_calendar_day_not_utc(): void
    {
        config(['app.user_timezone' => 'Europe/Madrid']);

        // En UTC este sería el próximo viaje; en Madrid ya empezó ayer.
        $this->trip($this->user, '2026-09-09', '2026-09-12');
        $today = $this->trip($this->user, '2026-09-10', '2026-09-15');

        // 23:30 UTC del día 9 = 01:30 del día 10 en Madrid.
        Carbon::setTestNow('2026-09-09 23:30:00');

        try {
            $this->actingAs($this->user)
                ->get('/')
                ->assertInertia(fn ($page) => $page
                    ->where('nextTrip.id', $today->id)
                    ->where('nextTrip.days_until', 0)
                );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_dashboard_counters_ignore_other_users_and_count_repeated_cities_once(): void
    {
        [, $madrid, $seville] = $this->countryWithCities('Spain', 'ES', ['Madrid', 'Seville']);
        [, $paris] = $this->countryWithCities('France', 'FR', ['Paris']);

        $t1 = $this->trip($this->user, '2026-01-01', '2026-01-10');
        $t2 = $this->trip($this->user, '2026-02-01', '2026-02-10');
        $this->visit($t1, $madrid, '2026-01-01');
        $this->visit($t2, $madrid, '2026-02-01');
        $this->visit($t2, $seville, '2026-02-02');
        $this->flight($t1, $madrid, $paris);

        $other = $this->trip(User::factory()->create(), '2026-03-01', '2026-03-02');
        $this->visit($other, $paris, '2026-03-01');
        $this->flight($other, $paris, $madrid);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('stats.countries', 1)
                ->where('stats.cities', 2)
                ->where('stats.trips', 2)
                ->where('stats.flights', 1)
            );
    }

    public function test_deleting_an_album_keeps_its_photos_and_other_users_cannot_delete_it(): void
    {
        $photo = $this->photo($this->user);
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Album', 'cover_photo_id' => $photo->id]);
        $album->photos()->attach($photo->id);

        $this->actingAs(User::factory()->create())
            ->delete("/albums/{$album->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('albums', ['id' => $album->id]);

        $this->actingAs($this->user)->delete("/albums/{$album->id}")->assertRedirect();

        $this->assertDatabaseMissing('albums', ['id' => $album->id]);
        $this->assertDatabaseMissing('album_photo', ['album_id' => $album->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id]);
    }

    public function test_removing_or_deleting_the_cover_photo_keeps_the_album_consistent(): void
    {
        $first = $this->photo($this->user);
        $second = $this->photo($this->user);
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Album', 'cover_photo_id' => $first->id]);
        $album->photos()->attach([$first->id, $second->id]);

        // Quitar la portada del álbum elige otra foto del álbum.
        $this->actingAs($this->user)->delete("/albums/{$album->id}/photos/{$first->id}")->assertRedirect();
        $this->assertSame($second->id, $album->fresh()->cover_photo_id);

        // Borrar la foto de portada deja el álbum sin portada, pero el álbum sigue.
        $this->actingAs($this->user)->delete("/photos/{$second->id}")->assertRedirect();
        $this->assertDatabaseHas('albums', ['id' => $album->id, 'cover_photo_id' => null]);
        $this->assertDatabaseHas('photos', ['id' => $first->id]);
    }

    public function test_photos_of_other_users_cannot_be_added_to_an_album(): void
    {
        $album = Album::create(['user_id' => $this->user->id, 'name' => 'Album']);
        $foreign = $this->photo(User::factory()->create());

        $this->actingAs($this->user)
            ->post("/albums/{$album->id}/photos", ['photo_ids' => [$foreign->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('album_photo', ['photo_id' => $foreign->id]);
        $this->assertNull($album->fresh()->cover_photo_id);
    }

    public function test_google_map_lists_of_other_users_cannot_be_attached_or_detached(): void
    {
        $trip = $this->trip($this->user, '2026-01-01', '2026-01-02');
        $mine = GoogleMapList::create(['user_id' => $this->user->id, 'name' => 'Mine', 'url' => 'https://maps.app.goo.gl/mine']);
        $foreign = GoogleMapList::create(['user_id' => User::factory()->create()->id, 'name' => 'Theirs', 'url' => 'https://maps.app.goo.gl/theirs']);
        $trip->googleMapLists()->attach($mine->id);

        $this->actingAs($this->user)->post("/trips/{$trip->id}/google-maps/{$foreign->id}")->assertForbidden();
        $this->actingAs($this->user)->delete("/trips/{$trip->id}/google-maps/{$foreign->id}")->assertForbidden();
        $this->assertDatabaseMissing('google_map_list_trip', ['google_map_list_id' => $foreign->id]);

        // Desvincular una lista propia no la borra.
        $this->actingAs($this->user)->delete("/trips/{$trip->id}/google-maps/{$mine->id}")->assertRedirect();
        $this->assertDatabaseMissing('google_map_list_trip', ['google_map_list_id' => $mine->id]);
        $this->assertDatabaseHas('google_map_lists', ['id' => $mine->id]);
    }

    public function test_deleting_a_visit_keeps_its_photos_in_the_trip(): void
    {
        [, $madrid] = $this->countryWithCities('Spain', 'ES', ['Madrid']);
        $trip = $this->trip($this->user, '2026-01-01', '2026-01-05');
        $visit = $this->visit($trip, $madrid, '2026-01-01');
        $photo = $this->photo($this->user, $trip, $visit);

        $this->actingAs($this->user)->delete("/trips/{$trip->id}/visits/{$visit->id}")->assertRedirect();

        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);
        $this->assertDatabaseHas('photos', ['id' => $photo->id, 'trip_id' => $trip->id, 'visit_id' => null]);
    }

    private function countryWithCities(string $name, string $iso, array $cities): array
    {
        $country = Country::create(['name' => $name, 'iso_code' => $iso]);

        return [$country, ...array_map(
            fn ($cityName) => City::create(['country_id' => $country->id, 'name' => $cityName, 'latitude' => 10, 'longitude' => 10]),
            $cities
        )];
    }

    private function trip(User $user, string $start, string $end): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => $start, 'end_date' => $end]);
    }

    private function visit(Trip $trip, City $city, string $date): Visit
    {
        return Visit::create(['user_id' => $trip->user_id, 'trip_id' => $trip->id, 'city_id' => $city->id, 'visited_from' => $date, 'visited_until' => $date]);
    }

    private function flight(Trip $trip, City $from, City $to): Flight
    {
        $airport = fn (City $city) => Airport::firstOrCreate(
            ['iata_code' => strtoupper(substr($city->name, 0, 3))],
            ['city_id' => $city->id, 'name' => "{$city->name} Airport", 'timezone' => 'Europe/Madrid']
        );

        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $airport($from)->id,
            'destination_airport_id' => $airport($to)->id,
            'flight_number' => 'XX1',
            'departure_at' => $trip->start_date->toDateString() . ' 08:00:00',
            'arrival_at' => $trip->start_date->toDateString() . ' 10:00:00',
        ]);
    }

    private function photo(User $user, ?Trip $trip = null, ?Visit $visit = null): Photo
    {
        return Photo::create([
            'user_id' => $user->id,
            'trip_id' => $trip?->id,
            'visit_id' => $visit?->id,
            'path' => 'photos/x/p.webp',
            'original_filename' => 'p.jpg',
            'processing_status' => 'ready',
        ]);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
