<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichCityRegions extends Command
{
    protected $signature = 'cities:enrich-regions';

    protected $description = 'Enrich ambiguous VyaMap cities with region codes using Geoapify';

    public function handle(): int
    {
        $ambiguousGroups = City::query()
            ->select('country_id', 'name')
            ->groupBy('country_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $autoMatch = 0;
        $review = 0;
        $noMatch = 0;
        $updated = 0;

        foreach ($ambiguousGroups as $group) {
            $cities = City::query()
                ->where('country_id', $group->country_id)
                ->where('name', $group->name)
                ->with('country')
                ->get();

            foreach ($cities as $city) {
                if ($city->region_code !== null) {
                    $this->line(
                        "{$city->name} | {$city->country->iso_code} | " .
                        "SKIP — region already set: {$city->region_code}"
                    );

                    continue;
                }

                $this->line(
                    "{$city->name} | {$city->country->iso_code} | " .
                    "{$city->latitude}, {$city->longitude}"
                );

                $results = $this->searchGeoapify(
                    $city->name,
                    $city->country->iso_code
                );

                if (empty($results)) {
                    $this->warn('  STATUS: NO_MATCH');
                    $noMatch++;

                    continue;
                }

                $matches = collect($results)
                    ->map(function (array $result) use ($city) {
                        return [
                            'result' => $result,
                            'distance' => $this->distanceKm(
                                (float) $city->latitude,
                                (float) $city->longitude,
                                (float) $result['lat'],
                                (float) $result['lon']
                            ),
                        ];
                    })
                    ->sortBy('distance')
                    ->values();

                $best = $matches->first();
                $result = $best['result'];
                $distance = $best['distance'];

                if ($distance <= 10) {
                    $autoMatch++;

                    $regionCode = $result['state_code'] ?? null;

                    if ($regionCode !== null) {
                        $city->update([
                            'region_code' => strtoupper($regionCode),
                        ]);

                        $updated++;

                        $this->info(
                            "  STATUS: AUTO_MATCH | " .
                            "REGION: {$regionCode} | " .
                            number_format($distance, 2) . " km"
                        );
                    } else {
                        $this->line(
                            "  STATUS: AUTO_MATCH | " .
                            "REGION: NULL | " .
                            "No region code from provider | " .
                            number_format($distance, 2) . " km"
                        );
                    }

                    continue;
                }

                $review++;

                $this->warn(
                    "  STATUS: REVIEW | " .
                    number_format($distance, 2) . " km"
                );
            }
        }

        $this->newLine();
        $this->line('================================');
        $this->line('SUMMARY');
        $this->line('================================');
        $this->line("AUTO_MATCH: {$autoMatch}");
        $this->line("UPDATED: {$updated}");
        $this->line("REVIEW: {$review}");
        $this->line("NO_MATCH: {$noMatch}");
        $this->line(
            'Cities with region_code: ' .
            City::whereNotNull('region_code')->count()
        );

        return self::SUCCESS;
    }

    private function searchGeoapify(
        string $city,
        string $countryIso
    ): array {
        $apiKey = config('services.geoapify.key');

        if (!$apiKey) {
            throw new \RuntimeException(
                'Geoapify API key is not configured.'
            );
        }

        $response = Http::get(
            'https://api.geoapify.com/v1/geocode/search',
            [
                'text' => "{$city}, {$countryIso}",
                'type' => 'city',
                'limit' => 20,
                'apiKey' => $apiKey,
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Geoapify request failed: ' . $response->status()
            );
        }

        return collect($response->json('features', []))
            ->map(function (array $feature) {
                $properties = $feature['properties'] ?? [];
                $coordinates = $feature['geometry']['coordinates'] ?? [];

                if (!isset($coordinates[0], $coordinates[1])) {
                    return null;
                }

                return [
                    'name' => $properties['name'] ?? null,
                    'city' => $properties['city'] ?? null,
                    'state_code' => $properties['state_code'] ?? null,
                    'lat' => $coordinates[1],
                    'lon' => $coordinates[0],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function distanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371;

        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a =
            sin($deltaLat / 2) ** 2 +
            cos($lat1) *
            cos($lat2) *
            sin($deltaLon / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }
}