<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Models\Visit;
use App\Services\PhotoFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Throwable;

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
                'coverPhoto:id,path,thumbnail_path,processing_status',
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
            // Sin coordenadas GPS ni metadatos internos: la galería no los usa.
            'photos' => $photos->map(fn (Photo $photo) => [
                'id' => $photo->id,
                'trip_id' => $photo->trip_id,
                'url' => $photo->fileUrl(),
                'thumbnail_url' => $photo->thumbnailUrl(),
                'original_filename' => $photo->original_filename,
                'width' => $photo->width,
                'height' => $photo->height,
                'taken_at' => $photo->taken_at?->toISOString(),
                'processing_status' => $photo->processing_status,
                'trip' => $photo->trip?->only(['id', 'name']),
                'albums' => $photo->albums->map->only(['id', 'name'])->values(),
            ])->values(),
            'albums' => $albums->map(fn ($album) => [
                'id' => $album->id,
                'name' => $album->name,
                'description' => $album->description,
                'cover_photo_id' => $album->cover_photo_id,
                'photos_count' => $album->photos_count,
                'cover_url' => $album->coverPhoto?->thumbnailUrl(),
            ])->values(),
            'trips' => $trips,
        ]);
    }

    public function store(StorePhotoRequest $request, PhotoFileManager $files): JsonResponse
    {
        $upload = $request->file('photo');

        // Una foto asociada a una visita pertenece siempre al viaje de esa visita.
        $visit = $request->filled('visit_id')
            ? Visit::find($request->validated('visit_id'))
            : null;

        $photo = Photo::create([
            'user_id' => $request->user()->id,
            'trip_id' => $request->validated('trip_id') ?? $visit?->trip_id,
            'visit_id' => $request->validated('visit_id'),
            'place_id' => $request->validated('place_id'),
            'journal_entry_id' => $request->validated('journal_entry_id'),
            'original_filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'path' => '',
            'processing_status' => 'pending',
        ]);

        try {
            // La extensión se deduce del contenido, no del nombre enviado.
            $temporaryPath = $upload->storeAs(
                $files->processingDirectory($photo->id),
                'original.' . $upload->extension(),
                'local'
            );

            if ($temporaryPath === false) {
                throw new RuntimeException('No se ha podido guardar la foto.');
            }
        } catch (Throwable $exception) {
            $photo->delete();

            throw $exception;
        }

        ProcessPhoto::dispatch(
            $photo->id,
            $temporaryPath
        );

        return response()->json([
            'message' => 'Photo uploaded successfully.',
            'photo' => [
                'id' => $photo->id,
                'trip_id' => $photo->trip_id,
                'original_filename' => $photo->original_filename,
                'processing_status' => $photo->processing_status,
            ],
        ], 201);
    }

    public function destroy(Request $request, Photo $photo, PhotoFileManager $files): RedirectResponse
    {
        abort_unless(
            $photo->user_id === $request->user()->id,
            403
        );

        $photoId = $photo->id;

        // Primero el registro (album_photo se borra en cascada) y después los
        // archivos: un fallo de almacenamiento solo deja archivos huérfanos.
        $photo->delete();

        $files->deleteFiles($photoId);

        return back();
    }
}