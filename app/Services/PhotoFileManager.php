<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ubicación y borrado de los archivos de una foto.
 *
 * - Original pendiente de procesar: disco "local", photo-processing/{id}/
 * - Imagen optimizada y miniatura: disco "public", photos/{id}/
 */
class PhotoFileManager
{
    public const PROCESSING_DIRECTORY = 'photo-processing';

    public const PUBLIC_DIRECTORY = 'photos';

    public function processingDirectory(int $photoId): string
    {
        return self::PROCESSING_DIRECTORY . '/' . $photoId;
    }

    public function publicDirectory(int $photoId): string
    {
        return self::PUBLIC_DIRECTORY . '/' . $photoId;
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
            Storage::disk('public')->deleteDirectory($this->publicDirectory($photoId));
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

    public function deletePublicFiles(int $photoId): void
    {
        Storage::disk('public')->deleteDirectory($this->publicDirectory($photoId));
    }

    public function deleteTemporaryFile(string $temporaryPath): void
    {
        if ($this->isProcessingPath($temporaryPath)) {
            Storage::disk('local')->delete($temporaryPath);
        }
    }

    private function isProcessingPath(string $path): bool
    {
        return str_starts_with($path, self::PROCESSING_DIRECTORY . '/')
            && ! str_contains($path, '..');
    }
}
