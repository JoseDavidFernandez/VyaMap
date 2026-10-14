<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rechaza imágenes demasiado grandes para decodificarlas con GD.
 *
 * Una imagen muy comprimida puede ocupar poco en disco y necesitar cientos
 * de MB al decodificarse (unos 4,5 bytes por píxel medidos con GD, fuera
 * del memory_limit de PHP). Solo se lee la cabecera con getimagesize().
 */
class ImageWithinPixelLimit implements ValidationRule
{
    /** Fotos: 50 MP (unos 220 MB al procesarlas). */
    public const PHOTO_MAX_PIXELS = 50_000_000;

    /** Avatar: 25 MP. */
    public const AVATAR_MAX_PIXELS = 25_000_000;

    public const MAX_SIDE = 12_000;

    public function __construct(
        private int $maxPixels,
        private int $maxSide = self::MAX_SIDE,
    ) {
    }

    public static function exceeds(int $width, int $height, int $maxPixels, int $maxSide = self::MAX_SIDE): bool
    {
        return $width <= 0
            || $height <= 0
            || $width > $maxSide
            || $height > $maxSide
            || $width * $height > $maxPixels;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false || self::exceeds($size[0], $size[1], $this->maxPixels, $this->maxSide)) {
            $fail(sprintf(
                'La imagen supera el tamaño máximo (%d megapíxeles y %d px por lado).',
                intdiv($this->maxPixels, 1_000_000),
                $this->maxSide,
            ));
        }
    }
}
