<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Models\Flight;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Protección contra peticiones de otro sitio (CSRF) en Laravel 13:
 * PreventRequestForgery (del que hereda VerifyCsrfToken) deja pasar una
 * escritura si el navegador la marca como Sec-Fetch-Site: same-origin
 * (cabecera que una página no puede falsificar) o si trae un token válido
 * (_token, X-CSRF-TOKEN o X-XSRF-TOKEN). En cualquier otro caso responde 419.
 *
 * Laravel desactiva esta comprobación en las pruebas; aquí se activa con
 * una subclase que no se considera en pruebas.
 */
class RequestForgeryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Trip $trip;

    private Flight $flight;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(VerifyCsrfToken::class, new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->user = User::factory()->create();
        $country = Country::create(['name' => 'Spain', 'iso_code' => 'ES']);
        $city = City::create(['country_id' => $country->id, 'name' => 'Madrid']);
        $mad = Airport::create(['city_id' => $city->id, 'name' => 'Barajas', 'iata_code' => 'MAD', 'timezone' => 'Europe/Madrid']);
        $bcn = Airport::create(['city_id' => $city->id, 'name' => 'El Prat', 'iata_code' => 'BCN', 'timezone' => 'Europe/Madrid']);

        $this->trip = Trip::create(['user_id' => $this->user->id, 'name' => 'Trip', 'start_date' => '2026-05-10', 'end_date' => '2026-05-20']);
        $this->flight = Flight::create(['user_id' => $this->user->id, 'origin_airport_id' => $mad->id, 'destination_airport_id' => $bcn->id, 'departure_date' => '2026-05-12']);
    }

    public function test_a_cross_site_request_without_a_token_is_rejected(): void
    {
        $this->attach(['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://evil.example'])->assertStatus(419);
        $this->attach(['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://other.vyamap.test'])->assertStatus(419);

        $this->assertNull($this->flight->fresh()->trip_id);
    }

    public function test_a_request_without_fetch_metadata_or_token_is_rejected(): void
    {
        // Navegadores antiguos o clientes que no envían Sec-Fetch-Site: necesitan el token.
        $this->attach(['Origin' => 'https://evil.example'])->assertStatus(419);
        $this->attach([])->assertStatus(419);

        $this->assertNull($this->flight->fresh()->trip_id);
    }

    public function test_a_same_origin_request_from_the_browser_passes_without_a_token(): void
    {
        $this->attach(['Sec-Fetch-Site' => 'same-origin'])->assertRedirect();

        $this->assertSame($this->trip->id, $this->flight->fresh()->trip_id);
    }

    public function test_a_valid_token_passes_even_without_fetch_metadata(): void
    {
        $this->actingAs($this->user)->withSession(['_token' => 'known-token']);

        $this->post("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$this->flight->id], '_token' => 'known-token'])
            ->assertRedirect();

        $this->assertSame($this->trip->id, $this->flight->fresh()->trip_id);
    }

    private function attach(array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)
            ->withHeaders($headers)
            ->postJson("/trips/{$this->trip->id}/flights/attach", ['flight_ids' => [$this->flight->id]]);
    }
}
