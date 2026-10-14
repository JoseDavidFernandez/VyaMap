<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import L from 'leaflet';

interface AirportPoint {
    code: string | null;
    airport: string;
    city: string;
    latitude: number;
    longitude: number;
}

interface Flight {
    id: number;
    flight_number: string;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    origin: AirportPoint;
    destination: AirportPoint;
}

const props = defineProps<{
    flights: Flight[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let layerGroup: L.LayerGroup | null = null;

type Point = [number, number];

// Un punto cada 2° de arco como máximo: curva suave sin miles de vértices.
const DEGREES_PER_STEP = 2;

const toRadians = (degrees: number) => (degrees * Math.PI) / 180;
const toDegrees = (radians: number) => (radians * 180) / Math.PI;

const isValidPoint = ([latitude, longitude]: Point) =>
    Number.isFinite(latitude) &&
    Number.isFinite(longitude) &&
    Math.abs(latitude) <= 90 &&
    Math.abs(longitude) <= 180;

/*
 * Puntos del círculo máximo (la ruta más corta sobre la esfera) entre dos
 * aeropuertos, con longitudes en [-180, 180].
 */
const greatCirclePoints = (origin: Point, destination: Point): Point[] => {
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
const splitAtAntimeridian = (points: Point[]): Point[][] => {
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

// Se redibuja al cambiar el periodo: el mapa sigue a los vuelos filtrados.
const renderRoutes = () => {
    if (!map) {
        return;
    }

    if (layerGroup) {
        layerGroup.clearLayers();
    } else {
        layerGroup = L.layerGroup().addTo(map);
    }

    const bounds = L.latLngBounds([]);

    props.flights.forEach((flight) => {
        const origin: Point = [
            Number(flight.origin.latitude),
            Number(flight.origin.longitude),
        ];

        const destination: Point = [
            Number(flight.destination.latitude),
            Number(flight.destination.longitude),
        ];

        // Sin coordenadas válidas no se puede dibujar ni la ruta ni sus marcadores.
        if (!isValidPoint(origin) || !isValidPoint(destination)) {
            return;
        }

        const route = splitAtAntimeridian(greatCirclePoints(origin, destination));

        route.flat().forEach((point) => bounds.extend(point));

        L.polyline(
            route,
            {
                color: '#ffffff',
                weight: 1,
                opacity: 0.35,
                dashArray: '5 7',
            },
        ).addTo(layerGroup!);

        L.circleMarker(origin, {
            radius: 4,
            color: '#ffffff',
            weight: 1.5,
            fillColor: '#111827',
            fillOpacity: 1,
        })
            .bindTooltip(
                `${flight.origin.city} · ${flight.origin.code ?? ''}`,
                {
                    direction: 'top',
                    offset: [0, -4],
                },
            )
            .addTo(layerGroup!);

        L.circleMarker(destination, {
            radius: 4,
            color: '#ffffff',
            weight: 1.5,
            fillColor: '#111827',
            fillOpacity: 1,
        })
            .bindTooltip(
                `${flight.destination.city} · ${flight.destination.code ?? ''}`,
                {
                    direction: 'top',
                    offset: [0, -4],
                },
            )
            .addTo(layerGroup!);
    });

    if (!bounds.isValid()) {
        map.setView([40.4168, -3.7038], 4);
    } else {
        map.fitBounds(bounds, {
            padding: [40, 40],
            maxZoom: 5,
        });
    }
};

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        preferCanvas: true,
    });

    L.control
        .zoom({
            position: 'bottomright',
        })
        .addTo(map);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        },
    ).addTo(map);

    renderRoutes();
});

watch(
    () => props.flights,
    () => {
        renderRoutes();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }

    layerGroup = null;
});
</script>

<template>
    <div
        ref="mapElement"
        class="flight-history-map"
    />
</template>

<style scoped>
.flight-history-map {
    width: 100%;
    height: 100%;
    min-height: 520px;
    background: #111827;
}

/*
 * OpenStreetMap no ofrece el estilo oscuro que buscamos.
 * Oscurecemos visualmente únicamente las teselas.
 * Los vuelos, puntos, controles y textos permanecen normales.
 */
.flight-history-map :deep(.leaflet-tile-pane) {
    filter:
        invert(1)
        hue-rotate(180deg)
        brightness(0.65)
        contrast(0.9)
        saturate(0.45);
}

.flight-history-map :deep(.leaflet-control-zoom) {
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: none;
}

.flight-history-map :deep(.leaflet-control-zoom a) {
    width: 32px;
    height: 32px;
    line-height: 30px;
    background: rgba(17, 24, 39, 0.9);
    color: #ffffff;
    border: 0;
}

.flight-history-map :deep(.leaflet-control-zoom a:hover) {
    background: rgba(31, 41, 55, 0.95);
}

.flight-history-map :deep(.leaflet-control-attribution) {
    background: rgba(17, 24, 39, 0.75);
    color: rgba(255, 255, 255, 0.55);
}

.flight-history-map :deep(.leaflet-control-attribution a) {
    color: rgba(255, 255, 255, 0.7);
}
</style>