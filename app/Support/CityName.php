<?php

namespace App\Support;

/**
 * Nombres de ciudad válidos. Geoapify devuelve a veces etiquetas de OSM en
 * lugar de un nombre (city = "yes" para la Ciudad del Vaticano): no son
 * válidos los valores vacíos, los que no tienen ninguna letra y esas
 * etiquetas.
 */
class CityName
{
    private const OSM_TAGS = ['yes', 'no', 'true', 'false'];

    public static function isValid(mixed $name): bool
    {
        if (! is_string($name)) {
            return false;
        }

        $name = trim($name);

        return $name !== ''
            && preg_match('/\p{L}/u', $name) === 1
            && ! in_array(mb_strtolower($name), self::OSM_TAGS, true);
    }

    /**
     * Primer candidato válido, sin espacios alrededor, o null si no hay ninguno.
     *
     * @param  list<mixed>  $candidates
     */
    public static function firstValid(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (self::isValid($candidate)) {
                return trim($candidate);
            }
        }

        return null;
    }
}
