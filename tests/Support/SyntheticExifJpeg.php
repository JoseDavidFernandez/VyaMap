<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Genera un JPEG sintético con un bloque EXIF mínimo construido a mano.
 *
 * Solo incluye DateTimeOriginal y las etiquetas GPS de latitud y longitud.
 * No contiene fabricante, modelo, MakerNote, miniatura ni otros metadatos.
 * Los valores por defecto son ficticios: un punto en el océano Pacífico.
 */
class SyntheticExifJpeg
{
    public const DATE_TIME_ORIGINAL = '2001:02:03 04:05:06';

    /** 12° 34' 56" N */
    public const LATITUDE = [12, 34, 56, 'N'];

    /** 98° 45' 54" W */
    public const LONGITUDE = [98, 45, 54, 'W'];

    public const EXPECTED_TAKEN_AT = '2001-02-03 04:05:06';

    public const EXPECTED_LATITUDE = 12.5822222;

    public const EXPECTED_LONGITUDE = -98.765;

    public static function bytes(
        int $width = 64,
        int $height = 48,
        string $dateTimeOriginal = self::DATE_TIME_ORIGINAL,
        array $latitude = self::LATITUDE,
        array $longitude = self::LONGITUDE,
    ): string {
        $jpeg = self::plainJpeg($width, $height);

        if (! str_starts_with($jpeg, "\xFF\xD8")) {
            throw new RuntimeException('GD no ha generado un JPEG válido.');
        }

        $tiff = self::tiff($dateTimeOriginal, $latitude, $longitude);
        $payload = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        // El segmento APP1 va inmediatamente después del marcador SOI.
        return "\xFF\xD8" . $app1 . substr($jpeg, 2);
    }

    public static function write(string $path, mixed ...$arguments): void
    {
        if (file_put_contents($path, self::bytes(...$arguments)) === false) {
            throw new RuntimeException("No se ha podido escribir {$path}.");
        }
    }

    private static function plainJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));

        ob_start();
        imagejpeg($image, null, 80);

        return (string) ob_get_clean();
    }

    /**
     * Estructura TIFF little-endian: IFD0 -> (Exif IFD, GPS IFD).
     */
    private static function tiff(string $dateTimeOriginal, array $latitude, array $longitude): string
    {
        if (strlen($dateTimeOriginal) !== 19) {
            throw new RuntimeException('DateTimeOriginal debe tener el formato YYYY:MM:DD HH:MM:SS.');
        }

        $ifd0Offset = 8;
        $ifd0Size = 2 + 2 * 12 + 4;
        $exifOffset = $ifd0Offset + $ifd0Size;
        $exifSize = 2 + 1 * 12 + 4;
        $dateOffset = $exifOffset + $exifSize;
        $gpsOffset = $dateOffset + 20;
        $gpsSize = 2 + 5 * 12 + 4;
        $latOffset = $gpsOffset + $gpsSize;
        $lonOffset = $latOffset + 24;

        $tiff = 'II' . pack('v', 42) . pack('V', $ifd0Offset);

        $tiff .= pack('v', 2)
            . self::entry(0x8769, 4, 1, pack('V', $exifOffset))
            . self::entry(0x8825, 4, 1, pack('V', $gpsOffset))
            . pack('V', 0);

        $tiff .= pack('v', 1)
            . self::entry(0x9003, 2, 20, pack('V', $dateOffset))
            . pack('V', 0);

        $tiff .= $dateTimeOriginal . "\x00";

        $tiff .= pack('v', 5)
            . self::entry(0x0000, 1, 4, "\x02\x02\x00\x00")
            . self::entry(0x0001, 2, 2, $latitude[3] . "\x00\x00\x00")
            . self::entry(0x0002, 5, 3, pack('V', $latOffset))
            . self::entry(0x0003, 2, 2, $longitude[3] . "\x00\x00\x00")
            . self::entry(0x0004, 5, 3, pack('V', $lonOffset))
            . pack('V', 0);

        $tiff .= self::rationals($latitude) . self::rationals($longitude);

        return $tiff;
    }

    private static function entry(int $tag, int $type, int $count, string $value): string
    {
        return pack('v', $tag) . pack('v', $type) . pack('V', $count) . str_pad($value, 4, "\x00");
    }

    private static function rationals(array $coordinate): string
    {
        return pack('V2', $coordinate[0], 1)
            . pack('V2', $coordinate[1], 1)
            . pack('V2', $coordinate[2], 1);
    }
}
