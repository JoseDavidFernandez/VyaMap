<?php

namespace App\Console\Commands;

use App\Models\City;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestCityRegionMatching extends Command
{
    protected $signature = 'cities:test-region-matching';

    protected $description = 'Test Geoapify region matching for ambiguous cities';

    public function handle(): int
    {
        $ambiguousCities = City::query()
            ->select('country_id', 'name')
            ->groupBy('country_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->with('country')
            ->get();

        $autoMatch = 0;
        $review = 0;
        $noMatch = 0;

        foreach ($ambiguousCities as $group) {
            $cities = City::query()
                ->where('country_id', $group->country_id)
                ->where('name', $group->name)
                ->orderBy('latitude')
                ->get();

            foreach ($cities as $city) {
                $this->line(
                    "{$city->name} | {$city->country->name} | " .
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
                        $distance = $this->distanceKm(
                            (float) $city->latitude,
                            (float) $city->longitude,
                            (float) $result['lat'],
                            (float) $result['lon']
                        );

                        return [
                            'result' => $result,
                            'distance' => $distance,
                        ];
                    })
                    ->sortBy('distance')
                    ->values();

                $best = $matches->first();

                if ($best['distance'] <= 10) {
                    $status = 'AUTO_MATCH';
                    $autoMatch++;
                } else {
                    $status = 'REVIEW';
                    $review++;
                }

                $result = $best['result'];

                $region = $result['state_code'] ?? '-';
                $name = $result['city'] ?? $result['name'] ?? '-';

                $this->line(
                    "  STATUS: {$status}"
                );

                $this->line(
                    "  BEST: {$region} | {$name} | " .
                    number_format($best['distance'], 2) . " km | " .
                    "{$result['lat']}, {$result['lon']}"
                );

                foreach ($matches->take(5) as $match) {
                    $candidate = $match['result'];

                    $candidateRegion = $candidate['state_code'] ?? '-';
                    $candidateName = $candidate['city']
                        ?? $candidate['name']
                        ?? '-';

                    $this->line(
                        "    {$candidateRegion} {$candidateName}" .
                        str_repeat(
                            ' ',
                            max(1, 30 - strlen($candidateName))
                        ) .
                        number_format($match['distance'], 2) .
                        " km | {$candidate['lat']}, {$candidate['lon']}"
                    );
                }

                $this->newLine();
            }
        }

        $this->newLine();
        $this->line('================================');
        $this->line('SUMMARY');
        $this->line('================================');
        $this->line("AUTO_MATCH: {$autoMatch}");
        $this->line("REVIEW: {$review}");
        $this->line("NO_MATCH: {$noMatch}");

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

                if (
                    !isset($coordinates[0], $coordinates[1])
                ) {
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