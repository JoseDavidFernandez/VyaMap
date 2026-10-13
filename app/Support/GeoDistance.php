<?php

namespace App\Support;

class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Distancia de círculo máximo (fórmula del haversine) en kilómetros.
     */
    public static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
