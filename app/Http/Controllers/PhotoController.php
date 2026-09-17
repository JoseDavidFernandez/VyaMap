<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;

class PhotoController extends Controller
{
    public function store(StorePhotoRequest $request): JsonResponse
    {
        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->validated('trip_id'),
            'visit_id' => $request->validated('visit_id'),
            'place_id' => $request->validated('place_id'),
            'journal_entry_id' => $request->validated('journal_entry_id'),
            'path' => $request->validated('path'),
            'original_filename' => $request->validated('original_filename'),
            'mime_type' => $request->validated('mime_type'),
            'size' => $request->validated('size'),
            'width' => $request->validated('width'),
            'height' => $request->validated('height'),
            'taken_at' => $request->validated('taken_at'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'metadata' => $request->validated('metadata'),
        ]);

        return response()->json($photo, 201);
    }
}