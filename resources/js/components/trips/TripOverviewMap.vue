<script setup lang="ts">

import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';

interface City {
    id: number;
    name: string;
    country: string;
    iso_code: string;
    latitude: number | null;
    longitude: number | null;
}

interface Visit {
    id: number;
    city: City;
}

const props = defineProps<{
    visits: Visit[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let markersLayer: L.LayerGroup | null = null;
let routeLayer: L.Polyline | null = null;

const validVisits = () =>
    props.visits.filter((visit) => {
        const latitude = Number(visit.city.latitude);
        const longitude = Number(visit.city.longitude);

        return Number.isFinite(latitude) && Number.isFinite(longitude);
    });

const createPinIcon = () =>
    L.divIcon({
        className: 'vyamap-map-pin-wrapper',
        html: '<div class="vyamap-map-pin">📍</div>',
        iconSize: [34, 42],
        iconAnchor: [17, 40],
        tooltipAnchor: [0, -34],
    });

const renderMap = () => {
    if (!map || !markersLayer) {
        return;
    }

    markersLayer.clearLayers();

    if (routeLayer) {
        routeLayer.removeFrom(map);
        routeLayer = null;
    }

    const visits = validVisits();
    const points: L.LatLngExpression[] = [];

    visits.forEach((visit) => {
        const latitude = Number(visit.city.latitude);
        const longitude = Number(visit.city.longitude);
        const point: L.LatLngExpression = [latitude, longitude];

        points.push(point);

        const marker = L.marker(point, {
            icon: createPinIcon(),
        });

        marker.bindTooltip(
            `<strong>${visit.city.name}</strong><br>${visit.city.country}`,
            {
                direction: 'top',
                offset: [0, -8],
                className: 'vyamap-map-tooltip',
            },
        );

        marker.addTo(markersLayer!);
    });

    if (points.length > 1) {
        routeLayer = L.polyline(points, {
            color: '#ffffff',
            weight: 1.5,
            opacity: 0.22,
            dashArray: '5 8',
            lineCap: 'round',
            lineJoin: 'round',
        }).addTo(map);
    }

    if (points.length === 0) {
        map.setView([40.4168, -3.7038], 4);
        return;
    }

    if (points.length === 1) {
        map.setView(points[0], 6);
        return;
    }

    const bounds = L.latLngBounds(points);

    if (bounds.isValid()) {
        map.fitBounds(bounds, {
            padding: [55, 55],
            maxZoom: 6,
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

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    L.control.zoom({
        position: 'bottomright',
    }).addTo(map);

    markersLayer = L.layerGroup().addTo(map);

    renderMap();

    window.setTimeout(() => {
        map?.invalidateSize();
        renderMap();
    }, 100);
});

watch(
    () => props.visits,
    () => {
        renderMap();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (map) {
        map.remove();
    }

    map = null;
    markersLayer = null;
    routeLayer = null;
});

</script>

<template>
    <div class="trip-overview-map relative h-full min-h-0 w-full overflow-hidden">
        <div ref="mapElement" class="h-full w-full"></div>

    </div>
</template>

<style scoped>
.trip-overview-map {
    background: #0b1015;
}

.trip-overview-map :deep(.leaflet-container) {
    height: 100%;
    width: 100%;
    background: #0b1015;
    font-family: inherit;
}

.trip-overview-map :deep(.leaflet-tile-pane) {
    filter: grayscale(0.15) saturate(0.55) contrast(0.95) brightness(1.02);
}

.trip-overview-map :deep(.leaflet-control-zoom) {
    margin: 0 14px 14px 0;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.28);
}

.trip-overview-map :deep(.leaflet-control-zoom a) {
    width: 32px;
    height: 32px;
    border: 0;
    background: rgba(10, 15, 20, 0.88);
    color: rgba(255, 255, 255, 0.65);
    line-height: 32px;
}

.trip-overview-map :deep(.leaflet-control-zoom a:hover) {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}

.trip-overview-map :deep(.leaflet-control-attribution) {
    margin: 0 8px 7px 0;
    padding: 2px 6px;
    border-radius: 5px;
    background: rgba(10, 15, 20, 0.65);
    color: rgba(255, 255, 255, 0.35);
    font-size: 9px;
}

.trip-overview-map :deep(.leaflet-control-attribution a) {
    color: rgba(255, 255, 255, 0.5);
}

.trip-overview-map :deep(.vyamap-map-pin-wrapper) {
    background: transparent;
    border: 0;
}

.trip-overview-map :deep(.vyamap-map-pin) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 42px;
    font-size: 30px;
    line-height: 1;
    filter: drop-shadow(0 5px 8px rgba(0, 0, 0, 0.45));
    transform-origin: center bottom;
    transition: transform 180ms ease;
}

.trip-overview-map :deep(.vyamap-map-pin:hover) {
    transform: scale(1.12) translateY(-2px);
}

.trip-overview-map :deep(.vyamap-map-tooltip) {
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    background: rgba(10, 15, 20, 0.94);
    color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.3);
    font-size: 11px;
    line-height: 1.5;
}

.trip-overview-map :deep(.vyamap-map-tooltip::before) {
    border-top-color: rgba(10, 15, 20, 0.94);
}
</style>
