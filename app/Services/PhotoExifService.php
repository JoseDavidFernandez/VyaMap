<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;

class PhotoExifService
{
    public function extract(string $path): array
    {
        if (! function_exists('exif_read_data')) {
            throw new RuntimeException('La extensión EXIF de PHP no está disponible.');
        }

        $exif = @exif_read_data($path, null, true);

        if ($exif === false) {
            return [];
        }

        return [
            'taken_at' => $this->extractTakenAt($exif),
            'latitude' => $this->extractCoordinate(
                $exif['GPS']['GPSLatitude'] ?? null,
                $exif['GPS']['GPSLatitudeRef'] ?? null
            ),
            'longitude' => $this->extractCoordinate(
                $exif['GPS']['GPSLongitude'] ?? null,
                $exif['GPS']['GPSLongitudeRef'] ?? null
            ),
        ];
    }

    private function extractTakenAt(array $exif): ?Carbon
    {
        $date = $exif['EXIF']['DateTimeOriginal']
            ?? $exif['EXIF']['DateTimeDigitized']
            ?? $exif['IFD0']['DateTime']
            ?? null;

        if (! $date) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', $date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractCoordinate(
        ?array $coordinate,
        ?string $reference
    ): ?float {
        if (
            ! $coordinate ||
            count($coordinate) !== 3 ||
            ! $reference
        ) {
            return null;
        }

        $degrees = $this->rationalToFloat($coordinate[0]);
        $minutes = $this->rationalToFloat($coordinate[1]);
        $seconds = $this->rationalToFloat($coordinate[2]);

        $decimal = $degrees
            + ($minutes / 60)
            + ($seconds / 3600);

        if (in_array(strtoupper($reference), ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }

    private function rationalToFloat(string|int|float $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! str_contains($value, '/')) {
            return (float) $value;
        }

        [$numerator, $denominator] = array_map(
            'floatval',
            explode('/', $value, 2)
        );

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $numerator / $denominator;
    }
}