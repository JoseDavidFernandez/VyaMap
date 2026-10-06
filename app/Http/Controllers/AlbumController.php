<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Photo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Album::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, Album $album): RedirectResponse
    {
        abort_unless($album->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $album->update($validated);

        return back();
    }

    public function destroy(Request $request, Album $album): RedirectResponse
    {
        abort_unless($album->user_id === $request->user()->id, 403);

        $album->delete();

        return back();
    }

    public function addPhotos(
        Request $request,
        Album $album
    ): RedirectResponse {
        abort_unless($album->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'exists:photos,id'],
        ]);

        $photoIds = Photo::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['photo_ids'])
            ->pluck('id');

        $album->photos()->syncWithoutDetaching($photoIds);

        if (!$album->cover_photo_id && $photoIds->isNotEmpty()) {
            $album->update([
                'cover_photo_id' => $photoIds->first(),
            ]);
        }

        return back();
    }

    public function removePhoto(
        Request $request,
        Album $album,
        Photo $photo
    ): RedirectResponse {
        abort_unless($album->user_id === $request->user()->id, 403);

        $album->photos()->detach($photo->id);

        if ($album->cover_photo_id === $photo->id) {
            $album->update([
                'cover_photo_id' => $album->photos()->value('photos.id'),
            ]);
        }

        return back();
    }
}