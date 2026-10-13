<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Country;
use App\Models\Trip;
use App\Models\User;
use App\Models\Visit;
use App\Support\SovereignCountries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SovereignCountriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_criterion_contains_the_193_un_member_states(): void
    {
        $this->assertCount(193, array_unique(config('countries.un_member_states')));
        $this->assertSame(193, SovereignCountries::total());
    }

    public function test_classification_of_sovereign_states_territories_and_special_codes(): void
    {
        $this->assertSame(SovereignCountries::SOVEREIGN, SovereignCountries::classify('ES'));
        $this->assertSame(SovereignCountries::SOVEREIGN, SovereignCountries::classify('jp'));

        $this->assertSame(SovereignCountries::TERRITORY, SovereignCountries::classify('PR'));
        $this->assertSame(SovereignCountries::TERRITORY, SovereignCountries::classify('RE'));
        $this->assertSame(SovereignCountries::TERRITORY, SovereignCountries::classify('AQ'));

        $this->assertSame(SovereignCountries::PENDING_DECISION, SovereignCountries::classify('TW'));
        $this->assertSame(SovereignCountries::PENDING_DECISION, SovereignCountries::classify('XK'));

        $this->assertSame(SovereignCountries::SPECIAL, SovereignCountries::classify('ZZ'));
        $this->assertSame(SovereignCountries::SPECIAL, SovereignCountries::classify('XP'));
    }

    public function test_lists_do_not_overlap(): void
    {
        $sovereign = config('countries.un_member_states');

        $this->assertEmpty(array_intersect($sovereign, config('countries.pending_decision')));
        $this->assertEmpty(array_intersect($sovereign, config('countries.special_codes')));
        $this->assertEmpty(array_intersect(config('countries.pending_decision'), config('countries.special_codes')));
    }

    public function test_only_distinct_sovereign_states_count_towards_the_percentage(): void
    {
        $codes = ['ES', 'es', 'FR', 'PR', 'TW', 'ZZ', 'XP', null];

        $this->assertSame(2, SovereignCountries::countVisited($codes));
        $this->assertSame(round(2 / 193 * 100, 1), SovereignCountries::percentage($codes));
        $this->assertSame(0.0, SovereignCountries::percentage([]));
    }

    public function test_additional_sovereign_states_can_be_enabled_by_configuration(): void
    {
        config(['countries.additional_sovereign' => ['VA']]);

        $this->assertTrue(SovereignCountries::isSovereign('VA'));
        $this->assertSame(194, SovereignCountries::total());
    }

    public function test_dashboard_percentage_uses_only_sovereign_states(): void
    {
        $user = $this->userWhoVisited(['ES', 'FR', 'PR', 'TW', 'ZZ']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                // El contador general sigue incluyendo territorios y códigos especiales.
                ->where('stats.countries', 5)
                ->where('stats.sovereign_countries', 2)
                ->where('stats.total_sovereign_countries', 193)
            );
    }

    public function test_dashboard_without_visits_reports_zero_sovereign_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.countries', 0)
                ->where('stats.sovereign_countries', 0)
                ->where('stats.total_sovereign_countries', 193)
            );
    }

    public function test_passport_marks_which_visits_are_in_sovereign_states(): void
    {
        $user = $this->userWhoVisited(['ES', 'PR', 'XP']);

        $this->actingAs($user)
            ->get('/passport')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Passport')
                ->where('totalCountries', 193)
                ->has('visits', 3)
                ->where('visits', fn ($visits) => collect($visits)
                    ->mapWithKeys(fn ($visit) => [$visit['iso_code'] => $visit['is_sovereign']])
                    ->sortKeys()
                    ->all() === ['ES' => true, 'PR' => false, 'XP' => false])
            );
    }

    public function test_classification_does_not_modify_the_catalog(): void
    {
        $this->userWhoVisited(['ES', 'ZZ']);

        $before = Country::query()->orderBy('id')->get()->toArray();

        SovereignCountries::percentage(Country::query()->pluck('iso_code'));

        $this->assertSame($before, Country::query()->orderBy('id')->get()->toArray());
    }

    private function userWhoVisited(array $isoCodes): User
    {
        $user = User::factory()->create();

        $trip = Trip::create([
            'user_id' => $user->id,
            'name' => 'Trip',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        foreach ($isoCodes as $index => $isoCode) {
            $country = Country::create(['name' => "Country {$isoCode}", 'iso_code' => $isoCode]);
            $city = City::create([
                'country_id' => $country->id,
                'name' => "City {$isoCode}",
                'latitude' => 10,
                'longitude' => 10,
            ]);

            Visit::create([
                'user_id' => $user->id,
                'trip_id' => $trip->id,
                'city_id' => $city->id,
                'visited_from' => sprintf('2026-01-%02d', $index + 1),
                'visited_until' => sprintf('2026-01-%02d', $index + 1),
            ]);
        }

        return $user;
    }
}
