<?php

namespace App\Http\Controllers;

use App\Services\CitySearchService;
use App\Http\Requests\SearchCityRequest;
use Illuminate\Http\JsonResponse;


class CitySearchController extends Controller
{
    public function __construct(
        private CitySearchService $citySearchService,
    ) {
    }

    public function __invoke(SearchCityRequest $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        return response()->json(
            $this->citySearchService->searchWithMeta($query, $request->boolean('external'))
        );
    }
}