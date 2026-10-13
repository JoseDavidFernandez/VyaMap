<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Support\CityAliasNormalizer;
use Illuminate\Support\Facades\DB;

class CityPersistenceService
{
    public function persist(array $data): City
    {
        return DB::transaction(function () use ($data) {
            $country = Country::query()->firstOrCreate(
                [
                    'iso_code' => strtoupper($data['iso_code']),
                ],
                [
                    'name' => $data['country'],
                ],
            );

            $city = City::query()->firstOrCreate(
                [
                    'external_provider' => $data['external_provider'],
                    'external_id' => $data['external_id'],
                ],
                [
                    'country_id' => $country->id,
                    'name' => $data['name'],
                    'region_code' => $data['region_code'] ?? null,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                ],
            );

            $searchQuery = trim((string) ($data['search_query'] ?? ''));

            $normalizedQuery = CityAliasNormalizer::normalize($searchQuery);
            $normalizedCityName = CityAliasNormalizer::normalize($city->name);

            if (
                $normalizedQuery !== ''
                && $normalizedQuery !== $normalizedCityName
            ) {
                $existingAlias = CityAlias::query()
                    ->where('normalized_alias', $normalizedQuery)
                    ->first();

                if (
                    $existingAlias === null
                    || $existingAlias->city_id === $city->id
                ) {
                    CityAlias::query()->firstOrCreate(
                        ['normalized_alias' => $normalizedQuery],
                        [
                            'city_id' => $city->id,
                            'alias' => $searchQuery,
                            'language' => null,
                        ],
                    );
                }
            }

            return $city;
        });
    }
}
