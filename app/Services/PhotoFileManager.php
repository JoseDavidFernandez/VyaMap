<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ubicación y borrado de los archivos de una foto.
 *
 * - Original pendiente de procesar: disco "local", photo-processing/{id}/
 * - Imagen optimizada y miniatura: disco privado "photos", photos/{id}/
 * - Fotos procesadas antes de la fase 04 (H1): disco "public", photos/{id}/,
 *   con la misma ruta relativa. Se leen y se borran ahí hasta que se migren.
 */
class PhotoFileManager
{
    public const DISK = 'photos';

    public const LEGACY_DISK = 'public';

    public const PROCESSING_DIRECTORY = 'photo-processing';

    public const OUTPUT_DIRECTORY = 'photos';

    public function processingDirectory(int $photoId): string
    {
        return self::PROCESSING_DIRECTORY . '/' . $photoId;
    }

    public function outputDirectory(int $photoId): string
    {
        return self::OUTPUT_DIRECTORY . '/' . $photoId;
    }

    /**
     * Disco que contiene el archivo (primero el privado), o null si no existe
     * o la ruta no pertenece a esa foto.
     */
    public function locate(int $photoId, ?string $path): ?string
    {
        if (! $this->isOutputPath($photoId, $path)) {
            return null;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    /**
     * Borra todos los archivos de una foto. Se llama después de borrar el
     * registro: si falla el almacenamiento, quedan archivos huérfanos (que
     * se pueden limpiar después) en lugar de registros que apunten a
     * archivos inexistentes.
     */
    public function deleteFiles(int $photoId, ?string $temporaryPath = null): bool
    {
        try {
            $this->deleteOutputFiles($photoId);
            Storage::disk('local')->deleteDirectory($this->processingDirectory($photoId));

            if ($temporaryPath !== null && $this->isProcessingPath($temporaryPath)) {
                Storage::disk('local')->delete($temporaryPath);
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('No se han podido borrar los archivos de una foto.', [
                'photo_id' => $photoId,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Imagen optimizada y miniatura, en el disco privado y en el antiguo.
     */
    public function deleteOutputFiles(int $photoId): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->outputDirectory($photoId));
        Storage::disk(self::LEGACY_DISK)->deleteDirectory($this->outputDirectory($photoId));
    }

    public function deleteTemporaryFile(string $temporaryPath): void
    {
        if ($this->isProcessingPath($temporaryPath)) {
            Storage::disk('local')->delete($temporaryPath);
        }
    }

    private function isOutputPath(int $photoId, ?string $path): bool
    {
        return $path !== null
            && str_starts_with($path, $this->outputDirectory($photoId) . '/')
            && ! str_contains($path, '..')
            && ! str_contains($path, "\0");
    }

    private function isProcessingPath(string $path): bool
    {
        return str_starts_with($path, self::PROCESSING_DIRECTORY . '/')
            && ! str_contains($path, '..');
    }
}
