<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * URLs legibles: /trips/{id}-{slug} y /countries/{slug}. El id manda, el slug
 * se deriva del nombre; las URL anteriores (/trips/386, /countries/1147) y los
 * slugs desactualizados redirigen a la canónica solo después de autorizar.
 */
class ReadableUrlTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->trip = Trip::create(['user_id' => $this->owner->id, 'name' => 'São Paulo & Río', 'start_date' => '2026-01-01', 'end_date' => '2026-01-05']);
    }

    public function test_the_trip_key_is_the_id_followed_by_a_slug_of_the_name(): void
    {
        $this->assertSame("{$this->trip->id}-sao-paulo-rio", $this->trip->getRouteKey());
        $this->assertSame(url("/trips/{$this->trip->id}-sao-paulo-rio"), route('trips.show', $this->trip));

        $emoji = Trip::create(['user_id' => $this->owner->id, 'name' => '🏝️', 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $this->assertSame((string) $emoji->id, $emoji->getRouteKey());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tripPages(): array
    {
        return ['detalle' => [''], 'edición' => ['/edit'], 'fotos' => ['/photos']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tripPages')]
    public function test_the_canonical_trip_url_opens_the_page(string $page): void
    {
        $this->actingAs($this->owner)
            ->get("/trips/{$this->trip->getRouteKey()}{$page}")
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tripPages')]
    public function test_old_and_outdated_trip_urls_redirect_permanently(string $page): void
    {
        $canonical = url("/trips/{$this->trip->getRouteKey()}{$page}");

        $this->actingAs($this->owner)->get("/trips/{$this->trip->id}{$page}")->assertRedirect($canonical)->assertStatus(301);
        $this->actingAs($this->owner)->get("/trips/{$this->trip->id}-old-name{$page}")->assertRedirect($canonical)->assertStatus(301);
    }

    public function test_the_query_string_survives_the_redirect(): void
    {
        $this->actingAs($this->owner)
            ->get("/trips/{$this->trip->id}?tab=flights")
            ->assertRedirect(url("/trips/{$this->trip->getRouteKey()}?tab=flights"));
    }

    public function test_renaming_a_trip_keeps_old_links_working(): void
    {
        $old = $this->trip->getRouteKey();
        $this->trip->update(['name' => 'Brazil 2026']);

        $this->actingAs($this->owner)
            ->get("/trips/{$old}")
            ->assertRedirect(url("/trips/{$this->trip->id}-brazil-2026"));
    }

    public function test_an_intruder_gets_403_without_a_redirect_that_reveals_the_name(): void
    {
        $intruder = User::factory()->create();

        foreach (["/trips/{$this->trip->id}", "/trips/{$this->trip->id}-guess", "/trips/{$this->trip->getRouteKey()}", "/trips/{$this->trip->id}/edit", "/trips/{$this->trip->id}/photos"] as $uri) {
            $response = $this->actingAs($intruder)->get($uri)->assertForbidden();

            $this->assertFalse($response->headers->has('Location'));
            $this->assertStringNotContainsString('sao-paulo', $response->getContent());
            $this->assertStringNotContainsString('São Paulo', $response->getContent());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return ['texto' => ['abc'], 'id pegado al texto' => ['12abc'], 'mayúsculas en el slug' => ['1-New-York'], 'id inexistente' => ['999999-x']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidKeys')]
    public function test_malformed_or_unknown_trip_keys_are_not_found(string $key): void
    {
        $this->actingAs($this->owner)->get("/trips/{$key}")->assertNotFound();
    }

    public function test_writes_accept_the_bare_id_and_the_full_key(): void
    {
        $payload = ['name' => 'São Paulo & Río', 'start_date' => '2026-01-01', 'end_date' => '2026-01-06'];

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->id}", $payload)
            ->assertRedirect(route('trips.show', $this->trip))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->owner)
            ->put("/trips/{$this->trip->getRouteKey()}", [...$payload, 'end_date' => '2026-01-07'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-01-07', $this->trip->fresh()->end_date->toDateString());
    }

    public function test_countries_use_their_name_as_key_and_old_ids_redirect(): void
    {
        $country = Country::create(['name' => "Côte d'Ivoire", 'iso_code' => 'CI']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Abidjan', 'latitude' => 5.3, 'longitude' => -4]);
        Visit::create(['user_id' => $this->owner->id, 'trip_id' => $this->trip->id, 'city_id' => $city->id, 'visited_from' => '2026-01-02', 'visited_until' => '2026-01-03']);

        $this->assertSame('cote-divoire', $country->getRouteKey());

        $this->actingAs($this->owner)
            ->get('/countries/cote-divoire')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Countries/Show')->where('country.id', $country->id));

        $this->actingAs($this->owner)
            ->get("/countries/{$country->id}")
            ->assertStatus(301)
            ->assertRedirect(url('/countries/cote-divoire'));

        $this->actingAs($this->owner)->get('/countries/atlantis')->assertNotFound();

        $this->actingAs($this->owner)
            ->get('/countries')
            ->assertInertia(fn ($page) => $page->where('countries.0.slug', 'cote-divoire'));
    }

    public function test_countries_whose_names_give_the_same_slug_get_their_iso_code(): void
    {
        $spain = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $this->assertSame('spain', $spain->getRouteKey());

        // Un país nuevo invalida el mapa de slugs calculado antes.
        $congo = Country::create(['name' => 'Congo', 'iso_code' => 'CG']);
        $twin = Country::create(['name' => 'Congo!', 'iso_code' => 'CD']);

        $this->assertSame('congo-cg', $congo->getRouteKey());
        $this->assertSame('congo-cd', $twin->getRouteKey());

        $this->actingAs($this->owner)->get('/countries/congo-cd')->assertOk()
            ->assertInertia(fn ($page) => $page->where('country.id', $twin->id));
        $this->actingAs($this->owner)->get('/countries/congo')->assertNotFound();
    }
}
