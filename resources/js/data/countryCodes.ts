/**
 * El GeoJSON de public/data/world.json (Natural Earth) usa en ISO_A2 algunos
 * códigos que no coinciden con los ISO 3166-1 de la tabla `countries`.
 */
const GEOJSON_TO_ISO: Record<string, string> = {
    'CN-TW': 'TW',
};

/**
 * Código ISO 3166-1 alfa-2 de una entidad del GeoJSON, o null si no tiene
 * (Natural Earth marca con "-99" las zonas sin código).
 */
export const normalizeGeoJsonIsoCode = (
    code: unknown,
): string | null => {
    if (code === null || code === undefined) {
        return null;
    }

    const value = String(code).toUpperCase();

    if (value === '' || value === '-99') {
        return null;
    }

    return GEOJSON_TO_ISO[value] ?? value;
};
