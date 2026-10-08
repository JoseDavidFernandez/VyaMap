<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';

interface FlightAirport {
    id: number;
    name: string;
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
    origin: FlightAirport;
    destination: FlightAirport;
}

const props = defineProps<{
    flights: Flight[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let layers: L.Layer[] = [];

const createAirportIcon = (active = false) => {
    return L.divIcon({
        className: '',
        html: `
            <div class="flight-airport-marker ${active ? 'active' : ''}">
                <div class="flight-airport-dot"></div>
            </div>
        `,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
};

const createRoute = (
    origin: L.LatLng,
    destination: L.LatLng,
): L.LatLngExpression[] => {
    const lat1 = origin.lat;
    const lng1 = origin.lng;
    const lat2 = destination.lat;
    const lng2 = destination.lng;

    const points: L.LatLngExpression[] = [];

    const segments = 40;

    const dx = lng2 - lng1;
    const dy = lat2 - lat1;

    const distance = Math.sqrt(dx * dx + dy * dy);

    /*
     * The larger the distance, the more pronounced
     * the curve becomes.
     */
    const curve = Math.min(distance * 0.12, 12);

    const midLat = (lat1 + lat2) / 2;
    const midLng = (lng1 + lng2) / 2;

    const offsetLat = -dx / Math.max(distance, 1) * curve;
    const offsetLng = dy / Math.max(distance, 1) * curve;

    const controlLat = midLat + offsetLat;
    const controlLng = midLng + offsetLng;

    for (let i = 0; i <= segments; i++) {
        const t = i / segments;
        const inverse = 1 - t;

        const lat =
            inverse * inverse * lat1 +
            2 * inverse * t * controlLat +
            t * t * lat2;

        const lng =
            inverse * inverse * lng1 +
            2 * inverse * t * controlLng +
            t * t * lng2;

        points.push([lat, lng]);
    }

    return points;
};

const buildTooltip = (flight: Flight) => {
    return `
        <div class="flight-map-tooltip">
            <div class="flight-map-tooltip-number">
                ${flight.flight_number}
            </div>

            ${
                flight.airline
                    ? `<div class="flight-map-tooltip-airline">${flight.airline}</div>`
                    : ''
            }

            <div class="flight-map-tooltip-route">
                <strong>${flight.origin.city}</strong>
                <span>→</span>
                <strong>${flight.destination.city}</strong>
            </div>
        </div>
    `;
};

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        minZoom: 2,
        maxZoom: 8,
        worldCopyJump: true,
        scrollWheelZoom: true,
        dragging: true,
        doubleClickZoom: true,
        touchZoom: true,
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
        },
    ).addTo(map);

    const bounds = L.latLngBounds([]);

    /*
     * Draw flight routes first.
     * This keeps the airports visually above the lines.
     */
    props.flights.forEach((flight) => {
        const origin = L.latLng(
            flight.origin.latitude,
            flight.origin.longitude,
        );

        const destination = L.latLng(
            flight.destination.latitude,
            flight.destination.longitude,
        );

        const route = createRoute(origin, destination);

        const shadow = L.polyline(route, {
            color: '#ffffff',
            weight: 5,
            opacity: 0.9,
            lineCap: 'round',
            lineJoin: 'round',
        }).addTo(map!);

        layers.push(shadow);

        const line = L.polyline(route, {
            color: '#1D2235',
            weight: 2,
            opacity: 0.7,
            dashArray: '2 7',
            lineCap: 'round',
            lineJoin: 'round',
        })
            .bindTooltip(buildTooltip(flight), {
                sticky: true,
                direction: 'top',
                className: 'flight-map-tooltip-container',
                opacity: 1,
            })
            .addTo(map!);

        layers.push(line);

        bounds.extend(origin);
        bounds.extend(destination);
    });

    /*
     * Airports
     */
    const airports = new Map<
        number,
        {
            airport: FlightAirport;
            type: 'origin' | 'destination';
        }
    >();

    props.flights.forEach((flight) => {
        airports.set(flight.origin.id, {
            airport: flight.origin,
            type: 'origin',
        });

        airports.set(flight.destination.id, {
            airport: flight.destination,
            type: 'destination',
        });
    });

    airports.forEach(({ airport, type }) => {
        const marker = L.marker(
            [airport.latitude, airport.longitude],
            {
                icon: createAirportIcon(type === 'origin'),
                zIndexOffset: 1000,
            },
        )
            .bindTooltip(
                `
                    <div class="airport-tooltip">
                        <div class="airport-tooltip-city">
                            ${airport.city}
                        </div>

                        <div class="airport-tooltip-name">
                            ${airport.name}
                        </div>
                    </div>
                `,
                {
                    direction: 'top',
                    offset: [0, -10],
                    className: 'airport-tooltip-container',
                    opacity: 1,
                },
            )
            .addTo(map!);

        layers.push(marker);

        bounds.extend([
            airport.latitude,
            airport.longitude,
        ]);
    });

    if (!bounds.isValid()) {
        map.setView([25, 0], 2);
    } else {
        map.fitBounds(bounds, {
            padding: [60, 60],
            maxZoom: 5,
        });
    }

    requestAnimationFrame(() => {
        map?.invalidateSize();
    });
});

onBeforeUnmount(() => {
    layers.forEach((layer) => layer.remove());

    layers = [];

    map?.remove();
    map = null;
});
</script>

<template>
    <div class="flight-history-map">
        <div ref="mapElement" class="h-full w-full"></div>

        <div class="flight-history-map-overlay">
            <div class="map-label">
                FLIGHT HISTORY
            </div>

            <div class="map-count">
                {{ flights.length }}
                {{ flights.length === 1 ? 'FLIGHT' : 'FLIGHTS' }}
            </div>
        </div>
    </div>
</template>

<style>
.flight-history-map {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #eef0f2;
}

.flight-history-map .leaflet-container {
    width: 100%;
    height: 100%;
    background: #eef0f2;
    font-family: inherit;
}

.flight-history-map .leaflet-tile-pane {
    filter:
        grayscale(1)
        contrast(0.88)
        brightness(1.04);
}

.flight-history-map .leaflet-control-zoom {
    border: 0 !important;
    box-shadow: none !important;
}

.flight-history-map .leaflet-control-zoom a {
    width: 34px !important;
    height: 34px !important;
    line-height: 32px !important;
    border: 1px solid rgba(29, 34, 53, 0.12) !important;
    background: rgba(255, 255, 255, 0.92) !important;
    color: #1d2235 !important;
    font-size: 16px !important;
}

.flight-history-map .leaflet-control-zoom a:first-child {
    border-radius: 10px 10px 0 0;
}

.flight-history-map .leaflet-control-zoom a:last-child {
    border-radius: 0 0 10px 10px;
    border-top: 0 !important;
}

.flight-history-map .leaflet-control-attribution {
    background: rgba(255, 255, 255, 0.72) !important;
    color: rgba(29, 34, 53, 0.5) !important;
    font-size: 9px !important;
}

.flight-history-map .leaflet-control-attribution a {
    color: rgba(29, 34, 53, 0.6) !important;
}

.flight-airport-marker {
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.flight-airport-marker::before {
    content: '';
    position: absolute;
    width: 17px;
    height: 17px;
    border-radius: 999px;
    border: 1px solid rgba(29, 34, 53, 0.28);
    background: rgba(255, 255, 255, 0.88);
}

.flight-airport-marker.active::before {
    border-color: #1d2235;
}

.flight-airport-dot {
    position: relative;
    z-index: 2;
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: #1d2235;
}

.flight-map-tooltip-container,
.airport-tooltip-container {
    border: 0 !important;
    border-radius: 10px !important;
    padding: 0 !important;
    box-shadow: 0 10px 30px rgba(29, 34, 53, 0.12) !important;
    background: transparent !important;
}

.flight-map-tooltip-container::before,
.airport-tooltip-container::before {
    display: none !important;
}

.flight-map-tooltip {
    min-width: 150px;
    padding: 12px 14px;
    border-radius: 10px;
    background: #1d2235;
    color: #ffffff;
}

.flight-map-tooltip-number {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.flight-map-tooltip-airline {
    margin-top: 3px;
    font-size: 10px;
    opacity: 0.58;
}

.flight-map-tooltip-route {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 9px;
    font-size: 11px;
}

.flight-map-tooltip-route span {
    opacity: 0.45;
}

.airport-tooltip {
    max-width: 220px;
    padding: 11px 13px;
    border-radius: 10px;
    background: #1d2235;
    color: #ffffff;
}

.airport-tooltip-city {
    font-size: 12px;
    font-weight: 700;
}

.airport-tooltip-name {
    margin-top: 3px;
    font-size: 10px;
    line-height: 1.35;
    opacity: 0.58;
}

.flight-history-map-overlay {
    position: absolute;
    z-index: 500;
    top: 18px;
    left: 18px;
    padding: 10px 12px;
    border: 1px solid rgba(29, 34, 53, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.86);
    backdrop-filter: blur(12px);
    pointer-events: none;
}

.map-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.16em;
    color: rgba(29, 34, 53, 0.48);
}

.map-count {
    margin-top: 2px;
    font-size: 11px;
    font-weight: 600;
    color: #1d2235;
}
</style>