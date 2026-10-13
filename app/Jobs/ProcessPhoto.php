<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\PhotoExifService;
use App\Services\PhotoFileManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $photoId,
        public string $temporaryPath,
    ) {
    }

    public function handle(PhotoExifService $exifService, PhotoFileManager $files): void
    {
        $photo = Photo::find($this->photoId);

        // La foto (o su viaje) se borró antes de procesarla: solo queda
        // eliminar el original pendiente.
        if ($photo === null) {
            $files->deleteTemporaryFile($this->temporaryPath);

            return;
        }

        if ($photo->processing_status === 'ready') {
            $files->deleteTemporaryFile($this->temporaryPath);

            return;
        }

        $photo->update([
            'processing_status' => 'processing',
        ]);

        try {
            $sourceDisk = Storage::disk('local');
            $publicDisk = Storage::disk('public');

            $sourcePath = $sourceDisk->path($this->temporaryPath);

            $exif = $exifService->extract($sourcePath);

            $photo->update([
                'taken_at' => $exif['taken_at'] ?? null,
                'latitude' => $exif['latitude'] ?? null,
                'longitude' => $exif['longitude'] ?? null,
            ]);

            $manager = new ImageManager(new Driver());

            $image = $manager->decodePath($sourcePath);

            $width = $image->width();
            $height = $image->height();

            $directory = $files->publicDirectory($photo->id);

            $filename = uniqid('photo_', true) . '.webp';
            $thumbnailFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.webp';

            $image->scaleDown(width: 1600);

            $encodedImage = $image->encode(
                new WebpEncoder(85)
            );

            $publicDisk->put(
                $directory . '/' . $filename,
                $encodedImage
            );

            $thumbnail = $manager
                ->decodePath($sourcePath)
                ->scaleDown(width: 400);

            $encodedThumbnail = $thumbnail->encode(
                new WebpEncoder(80)
            );

            $publicDisk->put(
                $directory . '/' . $thumbnailFilename,
                $encodedThumbnail
            );

            $photo->update([
                'path' => $directory . '/' . $filename,
                'thumbnail_path' => $directory . '/' . $thumbnailFilename,
                'size' => $publicDisk->size($directory . '/' . $filename),
                'width' => $width,
                'height' => $height,
                'processing_status' => 'ready',
            ]);

            $files->deleteTemporaryFile($this->temporaryPath);
        } catch (Throwable $exception) {
            // No dejamos imágenes a medio generar; el original se conserva
            // por si la cola reintenta el trabajo.
            $files->deletePublicFiles($photo->id);

            $photo->update([
                'processing_status' => 'failed',
                'metadata' => [
                    'processing_error' => $exception::class,
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * Se ejecuta cuando la cola agota los reintentos: el original (que puede
     * contener EXIF con ubicación) no debe quedarse huérfano.
     */
    public function failed(?Throwable $exception = null): void
    {
        app(PhotoFileManager::class)->deleteTemporaryFile($this->temporaryPath);
    }
}
