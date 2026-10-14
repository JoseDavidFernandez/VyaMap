<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Normaliza los avatares: se recodifican a WebP de como mucho 512 px, lo
 * que descarta los metadatos del original (EXIF, GPS, XMP). El original
 * subido nunca se guarda.
 */
class AvatarImageService
{
    public const DIRECTORY = 'avatars';

    public const MAX_SIZE = 512;

    /**
     * Guarda el avatar en el disco público y devuelve su ruta.
     */
    public function store(UploadedFile $upload): string
    {
        $image = (new ImageManager(new Driver()))->decodePath($upload->getRealPath());

        $image->scaleDown(width: self::MAX_SIZE, height: self::MAX_SIZE);

        $path = self::DIRECTORY . '/' . Str::random(40) . '.webp';

        Storage::disk('public')->put($path, (string) $image->encode(new WebpEncoder(85)));

        return $path;
    }

    /**
     * Borra un avatar anterior; solo dentro del directorio de avatares.
     */
    public function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::DIRECTORY . '/') && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }
}
