<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Album;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\GoogleMapList;
use App\Models\Journal;
use App\Models\JournalDay;
use App\Models\JournalEntry;
use App\Models\Photo;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Flujos de viajes, vuelos y visitas no cubiertos por las demás pruebas:
 * lectura entre usuarios, borrado en cascada, vuelos de otro viaje, orden
 * cronológico y fechas locales frente a UTC.
 */
class TripFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Trip $trip;

    private City $madrid;

    private Airport $mad;

    private Airport $bcn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();

        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $this->madrid = City::create(['country_id' => $spain->id, 'name' => 'Madrid']);
        $barcelona = City::create(['country_id' => $spain->id, 'name' => 'Barcelona']);

        $this->mad = Airport::create(['city_id' => $this->madrid->id, 'name' => 'Madrid', 'iata_code' => 'MAD', 'timezone' => 'Europe/Madrid']);
        $this->bcn = Airport::create(['city_id' => $barcelona->id, 'name' => 'Barcelona', 'iata_code' => 'BCN', 'timezone' => 'Europe/Madrid']);

        $this->trip = $this->createTrip($this->owner, '2026-09-10', '2026-09-12');
    }

    public function test_other_users_cannot_see_or_edit_a_trip(): void
    {
        $intruder = User::factory()->create();

        foreach (['', '/edit', '/photos'] as $suffix) {
            $this->actingAs($intruder)
                ->get("/trips/{$this->trip->id}{$suffix}")
                ->assertForbidden();
        }
    }

    public function test_trip_list_only_contains_the_users_own_trips(): void
    {
        $other = $this->createTrip(User::factory()->create(), '2026-01-01', '2026-01-02');

        $this->actingAs($this->owner)
            ->get('/trips')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Trips/Index')
                ->has('trips', 1)
                ->where('trips.0.id', $this->trip->id)
            );

        $this->assertNotSame($other->id, $this->trip->id);
    }

    // F1 (D2): los vuelos ya no se borran con el viaje; pasan a ser independientes.
    public function test_deleting_a_trip_deletes_its_visits_and_keeps_its_flights_as_standalone(): void
    {
        $visit = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');
        $flight = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $otherTrip = $this->createTrip($this->owner, '2026-10-01', '2026-10-05');
        $otherVisit = $this->createVisit($otherTrip, '2026-10-01', '2026-10-02');

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}")
            ->assertRedirect('/trips');

        $this->assertDatabaseMissing('trips', ['id' => $this->trip->id]);
        $this->assertDatabaseMissing('visits', ['id' => $visit->id]);

        // El viaje estaba completado: el vuelo, que ya contaba como realizado,
        // se conserva sin viaje y confirmado.
        $kept = $flight->fresh();
        $this->assertNotNull($kept);
        $this->assertNull($kept->trip_id);
        $this->assertNotNull($kept->confirmed_at);
        $this->assertSame('2026-09-10 06:00:00', $kept->departure_at->utc()->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('trips', ['id' => $otherTrip->id]);
        $this->assertDatabaseHas('visits', ['id' => $otherVisit->id]);
        $this->assertDatabaseHas('cities', ['id' => $this->madrid->id]);
        $this->assertDatabaseHas('airports', ['id' => $this->mad->id]);
    }

    /**
     * Borrado completo: todo lo que cuelga del viaje desaparece (cascadas de
     * la base y archivos de las fotos); álbumes, listas, ciudades y
     * aeropuertos se conservan y solo pierden la relación con el viaje. Los
     * vuelos también se conservan, como independientes (F1, D2).
     */
    public function test_deleting_a_trip_removes_every_dependent_row_and_photo_file(): void
    {
        $target = $this->createTripGraph($this->trip);
        $other = $this->createTrip($this->owner, '2026-10-01', '2026-10-05');
        $otherPhoto = $this->createPhotoWithFiles($other);

        // El álbum también contiene una foto de otro viaje y tiene como portada
        // una foto del viaje que se borra; la lista también está en otro viaje.
        $target['album']->photos()->attach($otherPhoto->id);
        $other->googleMapLists()->attach($target['list']->id);

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}")
            ->assertRedirect('/trips');

        $this->assertDatabaseMissing('trips', ['id' => $this->trip->id]);
        $this->assertDatabaseMissing('visits', ['id' => $target['visit']->id]);
        // F1 (D2): el vuelo se conserva como independiente.
        $this->assertDatabaseHas('flights', ['id' => $target['flight']->id, 'trip_id' => null]);
        $this->assertDatabaseMissing('journals', ['id' => $target['journal']->id]);
        $this->assertDatabaseMissing('journal_days', ['id' => $target['day']->id]);
        $this->assertDatabaseMissing('journal_entries', ['id' => $target['entry']->id]);

        foreach ($target['photos'] as $photo) {
            $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
            $this->assertDatabaseMissing('album_photo', ['photo_id' => $photo->id]);
            Storage::disk('photos')->assertMissing("photos/{$photo->id}");
            Storage::disk('local')->assertMissing("photo-processing/{$photo->id}");
        }

        $this->assertDatabaseMissing('google_map_list_trip', ['trip_id' => $this->trip->id]);

        // Entidades compartidas: siguen existiendo, solo sin la relación.
        $this->assertDatabaseHas('albums', ['id' => $target['album']->id, 'cover_photo_id' => null]);
        $this->assertDatabaseHas('album_photo', ['album_id' => $target['album']->id, 'photo_id' => $otherPhoto->id]);
        $this->assertDatabaseHas('google_map_lists', ['id' => $target['list']->id]);
        $this->assertDatabaseHas('google_map_list_trip', ['trip_id' => $other->id, 'google_map_list_id' => $target['list']->id]);
        $this->assertDatabaseHas('cities', ['id' => $this->madrid->id]);
        $this->assertDatabaseHas('airports', ['id' => $this->mad->id]);
        $this->assertDatabaseHas('airports', ['id' => $this->bcn->id]);
        Storage::disk('photos')->assertExists($otherPhoto->path);
    }

    /**
     * Aislamiento: otro viaje del mismo usuario y el viaje de otro usuario,
     * con todos sus datos y archivos, quedan exactamente igual.
     */
    public function test_deleting_a_trip_leaves_other_trips_of_any_user_untouched(): void
    {
        $this->createTripGraph($this->trip);

        $sameUser = $this->createTripGraph($this->createTrip($this->owner, '2026-10-01', '2026-10-05'));
        $stranger = User::factory()->create();
        $otherUser = $this->createTripGraph($this->createTrip($stranger, '2026-09-10', '2026-09-12'));

        $before = $this->rowsOf([$sameUser, $otherUser]);

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}")
            ->assertRedirect('/trips');

        $this->assertSame($before, $this->rowsOf([$sameUser, $otherUser]));

        foreach ([$sameUser, $otherUser] as $graph) {
            foreach ($graph['photos'] as $photo) {
                $photo->processing_status === 'ready'
                    ? Storage::disk('photos')->assertExists([$photo->path, $photo->thumbnail_path])
                    : Storage::disk('local')->assertExists("photo-processing/{$photo->id}/original.jpg");
            }
        }
    }

    public function test_a_flight_cannot_be_changed_through_another_trip_of_the_same_user(): void
    {
        $otherTrip = $this->createTrip($this->owner, '2026-09-10', '2026-09-12');
        $flight = $this->createFlight($otherTrip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}/flights/{$flight->id}", $this->flightPayload('VY9999', '2026-09-11T10:00', '2026-09-11T11:10'))
            ->assertNotFound();

        $this->actingAs($this->owner)
            ->delete("/trips/{$this->trip->id}/flights/{$flight->id}")
            ->assertNotFound();

        $this->assertSame('VY1000', $flight->fresh()->flight_number);
        $this->assertSame($otherTrip->id, $flight->fresh()->trip_id);
    }

    public function test_trip_page_lists_flights_in_chronological_order(): void
    {
        // El vuelo de vuelta se crea antes que el de ida.
        $return = $this->createFlight($this->trip, '2026-09-12 18:00:00', '2026-09-12 19:10:00', 'VY2000');
        $outbound = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00', 'VY1000');

        $this->actingAs($this->owner)
            ->get(route('trips.show', $this->trip))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('flights.0.id', $outbound->id)
                ->where('flights.1.id', $return->id)
                ->where('flights.0.departure', '2026-09-10T06:00:00.000000Z')
            );
    }

    public function test_trip_page_lists_visits_in_chronological_order(): void
    {
        $second = $this->createVisit($this->trip, '2026-09-11', '2026-09-12');
        $first = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');

        $this->actingAs($this->owner)
            ->get(route('trips.show', $this->trip))
            ->assertInertia(fn ($page) => $page
                ->where('visits.0.id', $first->id)
                ->where('visits.1.id', $second->id)
            );
    }

    public function test_flight_dates_are_compared_with_the_trip_in_local_airport_time(): void
    {
        // 23:30 en Madrid el último día del viaje (21:30 UTC): dentro del viaje.
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/flights", $this->flightPayload('VY1', '2026-09-12T22:00', '2026-09-12T23:30'))
            ->assertRedirect();

        // 00:30 en Madrid del día siguiente (22:30 UTC del último día): fuera del
        // viaje por la fecha local de salida. Se guarda y se avisa (D3).
        $this->actingAs($this->owner)
            ->postJson("/trips/{$this->trip->id}/flights", $this->flightPayload('VY2', '2026-09-13T00:30', '2026-09-13T01:40'))
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->get("/trips/{$this->trip->getRouteKey()}")
            ->assertInertia(fn ($page) => $page
                ->where('flights.0.flight_number', 'VY1')
                ->where('flights.0.outside_trip_dates', false)
                ->where('flights.1.flight_number', 'VY2')
                ->where('flights.1.outside_trip_dates', true));
    }

    public function test_flight_crossing_the_date_line_is_valid_when_it_arrives_after_departing_in_real_time(): void
    {
        $japan = Country::create(['name' => 'Japan', 'iso_code' => 'JP']);
        $usa = Country::create(['name' => 'United States', 'iso_code' => 'US']);
        $hnd = Airport::create(['city_id' => City::create(['country_id' => $japan->id, 'name' => 'Tokyo'])->id, 'name' => 'Haneda', 'iata_code' => 'HND', 'timezone' => 'Asia/Tokyo']);
        $hnl = Airport::create(['city_id' => City::create(['country_id' => $usa->id, 'name' => 'Honolulu'])->id, 'name' => 'Honolulu', 'iata_code' => 'HNL', 'timezone' => 'Pacific/Honolulu']);

        // Sale el 11 a las 21:00 en Tokio y llega el 11 a las 09:00 en Honolulu
        // (hora local anterior, pero 7 h después en tiempo real).
        $this->actingAs($this->owner)
            ->post("/trips/{$this->trip->id}/flights", [
                'origin_airport_id' => $hnd->id,
                'destination_airport_id' => $hnl->id,
                'flight_number' => 'NH186',
                'departure_date' => '2026-09-11', 'departure_time' => '21:00',
                'arrival_date' => '2026-09-11', 'arrival_time' => '09:00',
            ])
            ->assertRedirect(route('trips.show', $this->trip))
            ->assertSessionHasNoErrors();

        $flight = Flight::where('flight_number', 'NH186')->firstOrFail();
        $this->assertSame('2026-09-11 12:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-11 19:00:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_editing_a_flight_reconverts_times_with_the_new_airports(): void
    {
        $jfk = Airport::create(['city_id' => $this->madrid->id, 'name' => 'New York JFK', 'iata_code' => 'JFK', 'timezone' => 'America/New_York']);
        $flight = $this->createFlight($this->trip, '2026-09-10 06:00:00', '2026-09-10 07:10:00');

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}/flights/{$flight->id}", [
                'origin_airport_id' => $this->mad->id,
                'destination_airport_id' => $jfk->id,
                'flight_number' => 'IB6251',
                'departure_date' => '2026-09-10', 'departure_time' => '12:00',
                'arrival_date' => '2026-09-10', 'arrival_time' => '14:30',
            ])
            ->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame($jfk->id, $flight->destination_airport_id);
        $this->assertSame('2026-09-10 10:00:00', $flight->departure_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-10 18:30:00', $flight->arrival_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_visit_cannot_be_moved_outside_the_trip_dates(): void
    {
        $visit = $this->createVisit($this->trip, '2026-09-10', '2026-09-11');

        $this->actingAs($this->owner)
            ->putJson("/trips/{$this->trip->id}/visits/{$visit->id}", [
                'city_id' => $this->madrid->id,
                'visited_from' => '2026-09-11',
                'visited_until' => '2026-09-14',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visited_until']);

        $this->assertSame('2026-09-11', $visit->fresh()->visited_until->format('Y-m-d'));
    }

    /**
     * Un viaje con todo lo que puede colgar de él: visita, vuelo, foto lista
     * (archivos en el disco privado), foto pendiente (original en el disco de
     * procesamiento), diario con día y entrada, foto de la entrada, álbum del
     * usuario con esas fotos (y portada) y una lista de Google Maps.
     *
     * @return array{trip: Trip, visit: Visit, flight: Flight, journal: Journal, day: JournalDay, entry: JournalEntry, photos: list<Photo>, album: Album, list: GoogleMapList}
     */
    private function createTripGraph(Trip $trip): array
    {
        $visit = $this->createVisit($trip, $trip->start_date->toDateString(), $trip->start_date->toDateString());
        $flight = Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->bcn->id,
            'flight_number' => 'VY' . $trip->id,
            'departure_at' => $trip->start_date->toDateString() . ' 08:00:00',
            'arrival_at' => $trip->start_date->toDateString() . ' 09:15:00',
        ]);

        $journal = Journal::create(['trip_id' => $trip->id, 'title' => 'Journal']);
        $day = JournalDay::create(['journal_id' => $journal->id, 'day_number' => 1, 'date' => $trip->start_date->toDateString()]);
        $entry = JournalEntry::create(['journal_day_id' => $day->id, 'title' => 'Entry', 'content' => 'Text', 'city_id' => $this->madrid->id, 'sort_order' => 0]);

        $tripPhoto = $this->createPhotoWithFiles($trip, ['visit_id' => $visit->id]);
        $journalPhoto = $this->createPhotoWithFiles($trip, ['journal_entry_id' => $entry->id]);
        $pendingPhoto = $this->createPendingPhotoWithOriginal($trip);

        $album = Album::create(['user_id' => $trip->user_id, 'name' => 'Album', 'cover_photo_id' => $tripPhoto->id]);
        $album->photos()->attach([$tripPhoto->id, $journalPhoto->id]);

        $list = GoogleMapList::create(['user_id' => $trip->user_id, 'name' => 'List', 'url' => 'https://maps.app.goo.gl/list' . $trip->id]);
        $trip->googleMapLists()->attach($list->id);

        return [
            'trip' => $trip, 'visit' => $visit, 'flight' => $flight,
            'journal' => $journal, 'day' => $day, 'entry' => $entry,
            'photos' => [$tripPhoto, $journalPhoto, $pendingPhoto],
            'album' => $album, 'list' => $list,
        ];
    }

    private function createPhotoWithFiles(Trip $trip, array $attributes = []): Photo
    {
        $photo = Photo::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'path' => '',
            'original_filename' => 'photo.jpg',
            'processing_status' => 'ready',
            ...$attributes,
        ]);

        $photo->update([
            'path' => "photos/{$photo->id}/photo.webp",
            'thumbnail_path' => "photos/{$photo->id}/photo_thumb.webp",
        ]);

        Storage::disk('photos')->put($photo->path, 'image');
        Storage::disk('photos')->put($photo->thumbnail_path, 'thumbnail');

        return $photo;
    }

    private function createPendingPhotoWithOriginal(Trip $trip): Photo
    {
        $photo = Photo::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'path' => '',
            'original_filename' => 'pending.jpg',
            'processing_status' => 'pending',
        ]);

        Storage::disk('local')->put("photo-processing/{$photo->id}/original.jpg", 'original');

        return $photo;
    }

    /**
     * Filas de varios grafos de viaje (y de sus pivotes), para comparar.
     */
    private function rowsOf(array $graphs): array
    {
        $rows = fn (string $table, string $column, array $ids) => DB::table($table)->whereIn($column, $ids)->orderBy($column)->get()->map(fn ($row) => (array) $row)->all();
        $ids = fn (string $key) => array_map(fn ($graph) => $graph[$key]->id, $graphs);
        $photoIds = array_merge(...array_map(fn ($graph) => array_map(fn ($photo) => $photo->id, $graph['photos']), $graphs));

        return [
            'trips' => $rows('trips', 'id', $ids('trip')),
            'visits' => $rows('visits', 'id', $ids('visit')),
            'flights' => $rows('flights', 'id', $ids('flight')),
            'journals' => $rows('journals', 'id', $ids('journal')),
            'journal_days' => $rows('journal_days', 'id', $ids('day')),
            'journal_entries' => $rows('journal_entries', 'id', $ids('entry')),
            'photos' => $rows('photos', 'id', $photoIds),
            'albums' => $rows('albums', 'id', $ids('album')),
            'album_photo' => $rows('album_photo', 'album_id', $ids('album')),
            'google_map_lists' => $rows('google_map_lists', 'id', $ids('list')),
            'google_map_list_trip' => $rows('google_map_list_trip', 'trip_id', $ids('trip')),
        ];
    }

    private function createTrip(User $user, string $start, string $end): Trip
    {
        return Trip::create(['user_id' => $user->id, 'name' => 'Trip', 'start_date' => $start, 'end_date' => $end]);
    }

    private function createVisit(Trip $trip, string $from, string $until): Visit
    {
        return Visit::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'city_id' => $this->madrid->id,
            'visited_from' => $from,
            'visited_until' => $until,
        ]);
    }

    private function createFlight(Trip $trip, string $departureUtc, string $arrivalUtc, string $number = 'VY1000'): Flight
    {
        return Flight::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->bcn->id,
            'flight_number' => $number,
            'departure_at' => $departureUtc,
            'arrival_at' => $arrivalUtc,
        ]);
    }

    private function flightPayload(string $number, string $departure, string $arrival): array
    {
        return [
            'origin_airport_id' => $this->mad->id,
            'destination_airport_id' => $this->bcn->id,
            'flight_number' => $number,
            // F1: fecha local de salida obligatoria; horas opcionales.
            'departure_date' => substr($departure, 0, 10),
            'departure_time' => substr($departure, 11, 5),
            'arrival_date' => substr($arrival, 0, 10),
            'arrival_time' => substr($arrival, 11, 5),
        ];
    }
}
