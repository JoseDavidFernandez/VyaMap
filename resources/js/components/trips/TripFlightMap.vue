<script setup lang="ts">

import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import { OSM_ATTRIBUTION, OSM_TILE_URL } from '../../data/mapTiles';
import type { FlightRecord as Flight } from '../../types/flights';
import { escapeHtml } from '../../utils/escapeHtml';

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
        // Sin coordenadas no hay ruta (Number(null) sería 0 y la llevaría a 0,0).
        if ([flight.origin.latitude, flight.origin.longitude, flight.destination.latitude, flight.destination.longitude].some((value) => value === null)) {
            return;
        }

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

        // Próximo en violeta; cancelado o pendiente de confirmar en gris; realizado en cian.
        const accent = flight.status === 'upcoming'
            ? '#A78BFA'
            : flight.status === 'cancelled' || flight.status === 'pending'
                ? 'rgba(166, 179, 202, 0.5)'
                : '#5EE7F7';

        const route = L.polyline([origin, destination], {
            color: accent,
            weight: 1,
            opacity: 0.6,
            dashArray: '4 7',
            interactive: false,
        });

        route.addTo(layerGroup!);

        // Origen: anillo cian. Destino: punto cian relleno.
        const originMarker = L.circleMarker(origin, {
            radius: 4,
            color: accent,
            weight: 1.5,
            fillColor: '#070B14',
            fillOpacity: 1,
        });

        originMarker.bindTooltip(
            `<strong>${escapeHtml(flight.origin.city)}</strong><br>${escapeHtml(flight.origin.airport)}`,
            {
                direction: 'top',
                offset: [0, -6],
                className: 'vyamap-flight-tooltip',
            },
        );

        originMarker.addTo(layerGroup!);

        const destinationMarker = L.circleMarker(destination, {
            radius: 5,
            color: '#070B14',
            weight: 2,
            fillColor: accent,
            fillOpacity: 1,
        });

        destinationMarker.bindTooltip(
            `<strong>${escapeHtml(flight.destination.city)}</strong><br>${escapeHtml(flight.destination.airport)}`,
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
        map.fitBounds(L.latLngBounds(bounds), {
            padding: [42, 42],
            maxZoom: 6,
        });
    } else {
        map.setView([40.4, -3.7], 4);
    }

    setTimeout(() => map?.invalidateSize(), 0);
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

    L.tileLayer(OSM_TILE_URL, {
        attribution: OSM_ATTRIBUTION,
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
    <div ref="mapElement" class="flight-history-map vyamap-leaflet-dark"></div>
</template>

<style scoped>
.flight-history-map {
    position: relative;
    height: 100%;
    min-height: 100%;
    width: 100%;
    overflow: hidden;
    background: #0A0F1B;
}

.flight-history-map :deep(.vyamap-flight-tooltip strong) {
    font-weight: 600;
}
</style>
