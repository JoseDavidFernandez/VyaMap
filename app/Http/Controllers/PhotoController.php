<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    public function store(StorePhotoRequest $request): JsonResponse
    {
        $file = $request->file('photo');

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->validated('trip_id'),
            'visit_id' => $request->validated('visit_id'),
            'place_id' => $request->validated('place_id'),
            'journal_entry_id' => $request->validated('journal_entry_id'),
            'path' => '',
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'processing_status' => 'pending',
        ]);

        $temporaryPath = 'photo-processing/' . $photo->id . '/' . Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('local')->putFileAs(
            dirname($temporaryPath),
            $file,
            basename($temporaryPath)
        );

        ProcessPhoto::dispatch($photo->id, $temporaryPath);

        return response()->json($photo->fresh(), 201);
    }
}