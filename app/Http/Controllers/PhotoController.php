<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PhotoController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        $photos = Photo::query()
            ->where('user_id', $user->id)
            ->where('processing_status', 'ready')
            ->with([
                'albums:id,name',
                'trip:id,name',
            ])
            ->latest()
            ->get();

        $albums = $user->albums()
            ->withCount('photos')
            ->with([
                'coverPhoto:id,path,thumbnail_path',
            ])
            ->latest()
            ->get();

        $trips = $user->trips()
            ->select([
                'id',
                'name',
                'start_date',
                'end_date',
            ])
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('Photos/Index', [
            'photos' => $photos,
            'albums' => $albums,
            'trips' => $trips,
        ]);
    }

    public function store(StorePhotoRequest $request): JsonResponse
    {
        $temporaryPath = $request->file('photo')->store(
            'photo-processing',
            'local'
        );

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->input('trip_id'),
            'visit_id' => $request->input('visit_id'),
            'place_id' => $request->input('place_id'),
            'journal_entry_id' => $request->input('journal_entry_id'),
            'original_filename' => $request->file('photo')->getClientOriginalName(),
            'mime_type' => $request->file('photo')->getMimeType(),
            'size' => $request->file('photo')->getSize(),
            'path' => '',
            'processing_status' => 'pending',
        ]);

        ProcessPhoto::dispatch(
            $photo->id,
            $temporaryPath
        );

        return response()->json([
            'message' => 'Photo uploaded successfully.',
            'photo' => $photo,
        ]);
    }

    public function destroy(Request $request, Photo $photo): RedirectResponse
    {
        abort_unless(
            $photo->user_id === $request->user()->id,
            403
        );

        Storage::disk('public')->deleteDirectory(
            'photos/' . $photo->id
        );

        if (
            $photo->path &&
            str_starts_with($photo->path, 'photo-processing/')
        ) {
            Storage::disk('local')->delete($photo->path);
        }

        $photo->albums()->detach();

        $photo->delete();

        return back();
    }
}