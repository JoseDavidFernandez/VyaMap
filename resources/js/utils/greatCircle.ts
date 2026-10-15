/*
 * Rutas aéreas como arcos de círculo máximo (la ruta más corta sobre la
 * esfera), compartidas por los mapas de vuelos de Leaflet.
 */

export type Point = [number, number];

// Un punto cada 2° de arco como máximo: curva suave sin miles de vértices.
const DEGREES_PER_STEP = 2;

const toRadians = (degrees: number) => (degrees * Math.PI) / 180;
const toDegrees = (radians: number) => (radians * 180) / Math.PI;

// Punto válido a partir de coordenadas que pueden faltar (null nunca se convierte en 0).
export const toPoint = (latitude: number | null | undefined, longitude: number | null | undefined): Point | null => {
    if (latitude === null || latitude === undefined || longitude === null || longitude === undefined) {
        return null;
    }

    const point: Point = [Number(latitude), Number(longitude)];

    return isValidPoint(point) ? point : null;
};

export const isValidPoint = ([latitude, longitude]: Point) =>
    Number.isFinite(latitude) &&
    Number.isFinite(longitude) &&
    Math.abs(latitude) <= 90 &&
    Math.abs(longitude) <= 180;

/*
 * Puntos del círculo máximo (la ruta más corta sobre la esfera) entre dos
 * aeropuertos, con longitudes en [-180, 180].
 */
export const greatCirclePoints = (origin: Point, destination: Point): Point[] => {
    const [lat1, lng1] = [toRadians(origin[0]), toRadians(origin[1])];
    const [lat2, lng2] = [toRadians(destination[0]), toRadians(destination[1])];

    // Distancia angular (haversine: estable también en rutas muy cortas).
    const angle =
        2 *
        Math.asin(
            Math.min(
                1,
                Math.sqrt(
                    Math.sin((lat2 - lat1) / 2) ** 2 +
                        Math.cos(lat1) *
                            Math.cos(lat2) *
                            Math.sin((lng2 - lng1) / 2) ** 2,
                ),
            ),
        );

    const sinAngle = Math.sin(angle);

    // Mismo punto, o antípodas exactas (el círculo máximo no es único):
    // solo los extremos.
    if (sinAngle < 1e-9) {
        return [origin, destination];
    }

    const steps = Math.max(1, Math.ceil(toDegrees(angle) / DEGREES_PER_STEP));
    const points: Point[] = [origin];

    for (let step = 1; step < steps; step++) {
        const fraction = step / steps;
        const a = Math.sin((1 - fraction) * angle) / sinAngle;
        const b = Math.sin(fraction * angle) / sinAngle;

        const x = a * Math.cos(lat1) * Math.cos(lng1) + b * Math.cos(lat2) * Math.cos(lng2);
        const y = a * Math.cos(lat1) * Math.sin(lng1) + b * Math.cos(lat2) * Math.sin(lng2);
        const z = a * Math.sin(lat1) + b * Math.sin(lat2);

        points.push([
            toDegrees(Math.atan2(z, Math.hypot(x, y))),
            toDegrees(Math.atan2(y, x)),
        ]);
    }

    points.push(destination);

    return points;
};

/*
 * Corta la ruta donde cruza ±180°: termina en un borde del mapa y sigue en el
 * opuesto, en lugar de atravesar el mapa entero.
 */
export const splitAtAntimeridian = (points: Point[]): Point[][] => {
    const segments: Point[][] = [[points[0]]];

    for (let index = 1; index < points.length; index++) {
        const [lat1, lng1] = points[index - 1];
        const [lat2, lng2] = points[index];

        if (Math.abs(lng2 - lng1) > 180) {
            const edge = lng1 > 0 ? 180 : -180;
            const unwrappedLng2 = lng2 + (lng1 > 0 ? 360 : -360);
            const fraction = (edge - lng1) / (unwrappedLng2 - lng1);
            const latitude = lat1 + fraction * (lat2 - lat1);

            segments[segments.length - 1].push([latitude, edge]);
            segments.push([[latitude, -edge]]);
        }

        segments[segments.length - 1].push(points[index]);
    }

    return segments;
};

// Puntos del arco ya cortados en ±180°, listos para L.polyline.
export const greatCircleRoute = (origin: Point, destination: Point): Point[][] =>
    splitAtAntimeridian(greatCirclePoints(origin, destination));
