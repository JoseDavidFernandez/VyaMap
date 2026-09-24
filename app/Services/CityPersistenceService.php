<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
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
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                ],
            );

            return $city;
        });
    }
}