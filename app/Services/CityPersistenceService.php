<?php

namespace App\Services;

use App\Models\City;
use App\Models\CityAlias;
use App\Models\Country;
use App\Support\CityAliasNormalizer;
use App\Support\GeoDistance;
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

            $city = $this->findExisting($data, $country)
                ?? City::query()->create([
                    'country_id' => $country->id,
                    'name' => $data['name'],
                    'region_code' => $data['region_code'] ?? null,
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'external_provider' => $data['external_provider'],
                    'external_id' => $data['external_id'],
                ]);

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

    /**
     * Evita duplicados: primero por identidad externa (siguiendo una posible
     * fusión) y después por una ciudad del mismo país, con el mismo nombre
     * normalizado y a menos de config('cities.same_city_max_distance_km').
     * Si esa ciudad aún no tiene identidad externa, se le asigna.
     */
    private function findExisting(array $data, Country $country): ?City
    {
        $byExternalId = City::query()
            ->where('external_provider', $data['external_provider'])
            ->where('external_id', $data['external_id'])
            ->first();

        if ($byExternalId !== null) {
            return $byExternalId->merged_into_id !== null
                ? City::query()->find($byExternalId->merged_into_id) ?? $byExternalId
                : $byExternalId;
        }

        $normalizedName = CityAliasNormalizer::normalize($data['name']);
        $maxDistance = (float) config('cities.same_city_max_distance_km', 25);

        // Recuadro aproximado para no cargar todas las ciudades del país.
        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];
        $latitudeDelta = $maxDistance / 111 + 0.01;
        $longitudeDelta = $latitudeDelta / max(cos(deg2rad($latitude)), 0.01);

        $nearby = City::query()
            ->notMerged()
            ->where('country_id', $country->id)
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->get()
            ->filter(fn (City $city) => CityAliasNormalizer::normalize($city->name) === $normalizedName)
            ->map(fn (City $city) => [
                'city' => $city,
                'distance' => GeoDistance::km(
                    (float) $city->latitude,
                    (float) $city->longitude,
                    $latitude,
                    $longitude,
                ),
            ])
            ->filter(fn (array $candidate) => $candidate['distance'] <= $maxDistance)
            ->sortBy('distance')
            ->first();

        if ($nearby === null) {
            return null;
        }

        $city = $nearby['city'];

        if ($city->external_id === null) {
            $city->forceFill([
                'external_provider' => $data['external_provider'],
                'external_id' => $data['external_id'],
            ])->save();
        }

        return $city;
    }
}
