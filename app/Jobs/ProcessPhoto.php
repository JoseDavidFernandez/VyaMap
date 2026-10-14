<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Rules\ImageWithinPixelLimit;
use App\Services\PhotoExifService;
use App\Services\PhotoFileManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    /** Reintentos ante fallos transitorios (disco, cola). */
    public int $tries = 3;

    /** Segundos de espera antes del 2.º y 3.er intento. */
    public array $backoff = [10, 60];

    /** Una foto de 24 MP tarda menos de 1 s; 120 s deja margen amplio. */
    public int $timeout = 120;

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

        // Defensa ante originales que no pasaron por StorePhotoRequest: una
        // imagen enorme agotaría la memoria del worker al decodificarla.
        $size = @getimagesize(Storage::disk('local')->path($this->temporaryPath));

        // Reintentar no cambiaría el resultado: falla sin más intentos. Si la
        // cabecera no se puede leer, la decodificación decide (con reintentos).
        if ($size !== false && ImageWithinPixelLimit::exceeds($size[0], $size[1], ImageWithinPixelLimit::PHOTO_MAX_PIXELS)) {
            $this->fail(new RuntimeException('La imagen supera el tamaño máximo.'));

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

            // La miniatura sale de la versión ya reducida: una sola decodificación
            // del original (más rápido y con menos memoria).
            $thumbnail = (clone $image)->scaleDown(width: 400);

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

            // Mientras queden reintentos la foto sigue pendiente; el estado
            // definitivo 'failed' lo fija failed() al agotarlos.
            $photo->update([
                'processing_status' => $this->attempts() < $this->tries ? 'pending' : 'failed',
                'metadata' => [
                    'processing_error' => $exception::class,
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * Se ejecuta cuando la cola agota los reintentos (o el worker murió y el
     * trabajo caducó): la foto queda como fallida, sin imágenes a medias, y el
     * original (que puede contener EXIF con ubicación) no se queda huérfano.
     */
    public function failed(?Throwable $exception = null): void
    {
        $files = app(PhotoFileManager::class);

        $photo = Photo::find($this->photoId);

        if ($photo !== null && $photo->processing_status !== 'ready') {
            $files->deletePublicFiles($photo->id);

            $photo->update([
                'processing_status' => 'failed',
                'metadata' => [
                    'processing_error' => $exception ? $exception::class : 'unknown',
                ],
            ]);
        }

        $files->deleteTemporaryFile($this->temporaryPath);
    }
}
