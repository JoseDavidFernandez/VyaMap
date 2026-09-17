<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;

class JournalEntryController extends Controller
{
    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $entry = JournalEntry::create([
            'journal_day_id' => $request->validated('journal_day_id'),
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'city_id' => $request->validated('city_id'),
            'place_id' => $request->validated('place_id'),
            'sort_order' => $request->validated('sort_order'),
        ]);

        return response()->json($entry, 201);
    }
}