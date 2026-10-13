<?php

namespace App\Support;

use App\Models\City;

/**
 * Decide si una búsqueda puede guardarse como alias global de una ciudad.
 *
 * Los alias son compartidos por todos los usuarios y una coincidencia exacta
 * con un alias hace que la búsqueda no consulte Geoapify, así que un alias
 * erróneo puede ocultar ciudades o llevar a elegir la equivocada.
 *
 * Un alias que coincide con el nombre de otra ciudad ("roma" → Rome, existiendo
 * Roma en Australia) se permite: las dos aparecen como coincidencias exactas,
 * con su país, y el usuario elige.
 */
class CityAliasPolicy
{
    public const MIN_LENGTH = 4;

    /**
     * Motivo por el que el alias no es aceptable, o null si lo es.
     */
    public static function rejectionReason(string $normalizedAlias, City $city): ?string
    {
        $cityName = CityAliasNormalizer::normalize($city->name);

        if (mb_strlen($normalizedAlias) < self::MIN_LENGTH) {
            return 'demasiado corto';
        }

        // Letras, espacios, puntos, apóstrofos y guiones; ni números, ni
        // paréntesis, ni comas (p. ej. "paris (francia)").
        if (! preg_match("/^[a-z](?:[a-z .'-]*[a-z.])?$/", $normalizedAlias)) {
            return 'caracteres no válidos';
        }

        if ($normalizedAlias === $cityName) {
            return 'igual al nombre de la ciudad';
        }

        // "rom" → "rome" es una búsqueda a medio escribir; "quebec" → "quebec city"
        // es un alias legítimo porque termina en un límite de palabra.
        if (str_starts_with($cityName, $normalizedAlias)) {
            $next = mb_substr($cityName, mb_strlen($normalizedAlias), 1);

            if ($next !== ' ' && $next !== '-') {
                return 'nombre incompleto';
            }
        }

        return null;
    }
}
