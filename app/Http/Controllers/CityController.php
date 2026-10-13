<?php

namespace App\Http\Controllers;

use App\Exceptions\GeoapifyUnavailableException;
use App\Http\Requests\StoreCityRequest;
use App\Services\CityPersistenceService;
use App\Services\CitySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CityController extends Controller
{
    public function __construct(
        private CityPersistenceService $cityPersistenceService,
        private CitySearchService $citySearchService,
    ) {
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $searchQuery = trim($request->validated('search_query'));

        try {
            $result = $this->citySearchService->verifyGeoapifyCity(
                $searchQuery,
                $request->validated('external_id'),
            );
        } catch (GeoapifyUnavailableException) {
            return response()->json([
                'message' => 'No se ha podido verificar la ciudad. Inténtalo de nuevo más tarde.',
            ], 503);
        }

        if (
            $result === null
            || ! preg_match('/^[A-Z]{2}$/', (string) $result['iso_code'])
        ) {
            throw ValidationException::withMessages([
                'external_id' => 'La ciudad seleccionada no se ha podido verificar.',
            ]);
        }

        $city = $this->cityPersistenceService->persist([
            'name' => $result['name'],
            'country' => $result['country'],
            'iso_code' => $result['iso_code'],
            'region_code' => $result['region_code'],
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'external_provider' => 'geoapify',
            'external_id' => $result['external_id'],
            'search_query' => $searchQuery,
        ]);

        return response()->json([
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
                'country_id' => $city->country_id,
                'latitude' => (float) $city->latitude,
                'longitude' => (float) $city->longitude,
                'external_provider' => $city->external_provider,
                'external_id' => $city->external_id,
            ],
        ], 201);
    }
}
