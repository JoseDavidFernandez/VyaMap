<script setup lang="ts">

import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
let layerGroup: L.LayerGroup | null = null;

const renderMap = async () => {
    if (!map) {
        return;
    }

    await nextTick();

    if (layerGroup) {
        layerGroup.clearLayers();
    } else {
        layerGroup = L.layerGroup().addTo(map);
    }

    const bounds: L.LatLngExpression[] = [];

    props.flights.forEach((flight) => {
        const origin: [number, number] = [
            Number(flight.origin.latitude),
            Number(flight.origin.longitude),
        ];

        const destination: [number, number] = [
            Number(flight.destination.latitude),
            Number(flight.destination.longitude),
        ];

        if (
            !Number.isFinite(origin[0]) ||
            !Number.isFinite(origin[1]) ||
            !Number.isFinite(destination[0]) ||
            !Number.isFinite(destination[1])
        ) {
            return;
        }

        const route = L.polyline([origin, destination], {
            color: '#ffffff',
            weight: 1,
            opacity: 0.45,
            dashArray: '4 7',
            interactive: false,
        });

        route.addTo(layerGroup!);

        const originMarker = L.circleMarker(origin, {
            radius: 4,
            color: '#ffffff',
            weight: 1,
            fillColor: '#111111',
            fillOpacity: 1,
        });

        originMarker.bindTooltip(
            `<strong>${escapeHtml(flight.origin.city)}</strong><br>${escapeHtml(flight.origin.name)}`,
            {
                direction: 'top',
                offset: [0, -6],
                className: 'vyamap-flight-tooltip',
            },
        );

        originMarker.addTo(layerGroup!);

        const destinationMarker = L.circleMarker(destination, {
            radius: 5,
            color: '#111111',
            weight: 2,
            fillColor: '#ffffff',
            fillOpacity: 1,
        });

        destinationMarker.bindTooltip(
            `<strong>${escapeHtml(flight.destination.city)}</strong><br>${escapeHtml(flight.destination.name)}`,
            {
                direction: 'top',
                offset: [0, -6],
                className: 'vyamap-flight-tooltip',
            },
        );

        destinationMarker.addTo(layerGroup!);

        bounds.push(origin, destination);
    });

    if (bounds.length) {
        map.fitBounds(bounds, {
            padding: [42, 42],
            maxZoom: 6,
        });
    } else {
        map.setView([40.4, -3.7], 4);
    }

    setTimeout(() => map?.invalidateSize(), 0);
};

const escapeHtml = (value: string | null | undefined) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
};

onMounted(async () => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        preferCanvas: true,
        worldCopyJump: true,
    });

    L.control.zoom({
        position: 'bottomright',
    }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
        crossOrigin: true,
    }).addTo(map);

    await renderMap();
});

watch(
    () => props.flights,
    () => {
        renderMap();
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
    <div ref="mapElement" class="flight-history-map"></div>
</template>

<style scoped>
.flight-history-map {
    position: relative;
    height: 100%;
    min-height: 100%;
    width: 100%;
    overflow: hidden;
    background: #101316;
}

.flight-history-map :deep(.leaflet-tile-pane) {
    filter: invert(1) hue-rotate(180deg) brightness(0.62) contrast(0.92) saturate(0.35);
}

.flight-history-map :deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 10px !important;
    background: rgba(15, 18, 21, 0.86) !important;
    box-shadow: none !important;
}

.flight-history-map :deep(.leaflet-control-zoom a) {
    width: 30px;
    height: 30px;
    border: 0 !important;
    background: transparent !important;
    color: rgba(255, 255, 255, 0.7) !important;
    line-height: 30px;
}

.flight-history-map :deep(.leaflet-control-zoom a:hover) {
    background: rgba(255, 255, 255, 0.08) !important;
    color: #ffffff !important;
}

.flight-history-map :deep(.leaflet-control-attribution) {
    padding: 2px 6px;
    border-radius: 4px 0 0 0;
    background: rgba(10, 12, 14, 0.65);
    color: rgba(255, 255, 255, 0.35);
    font-size: 8px;
}

.flight-history-map :deep(.leaflet-control-attribution a) {
    color: rgba(255, 255, 255, 0.5);
}

.flight-history-map :deep(.vyamap-flight-tooltip) {
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: rgba(15, 18, 21, 0.95);
    color: rgba(255, 255, 255, 0.82);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
    font-size: 10px;
    line-height: 1.5;
}

.flight-history-map :deep(.vyamap-flight-tooltip strong) {
    color: #ffffff;
    font-weight: 600;
}

.flight-history-map :deep(.leaflet-tooltip-top:before) {
    border-top-color: rgba(15, 18, 21, 0.95);
}
</style>
