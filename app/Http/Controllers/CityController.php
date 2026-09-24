<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCityRequest;
use App\Services\CityPersistenceService;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    public function __construct(
        private CityPersistenceService $cityPersistenceService,
    ) {
    }

    public function store(StoreCityRequest $request): JsonResponse
    {
        $city = $this->cityPersistenceService->persist(
            $request->validated()
        );

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