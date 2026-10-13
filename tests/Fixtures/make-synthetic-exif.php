<?php

/**
 * Regenera tests/Fixtures/synthetic-exif.jpg.
 *
 * Uso: php tests/Fixtures/make-synthetic-exif.php
 *
 * La imagen es un rectángulo de color generado con GD y un bloque EXIF
 * ficticio (ver Tests\Support\SyntheticExifJpeg). No procede de ninguna
 * cámara ni contiene datos personales.
 */

require __DIR__ . '/../../vendor/autoload.php';

Tests\Support\SyntheticExifJpeg::write(__DIR__ . '/synthetic-exif.jpg');

echo "Generado: tests/Fixtures/synthetic-exif.jpg\n";
