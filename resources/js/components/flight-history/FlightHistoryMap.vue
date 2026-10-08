<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
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

    const bounds = L.latLngBounds([]);

    props.flights.forEach((flight) => {
        const origin: L.LatLngExpression = [
            flight.origin.latitude,
            flight.origin.longitude,
        ];

        const destination: L.LatLngExpression = [
            flight.destination.latitude,
            flight.destination.longitude,
        ];

        bounds.extend(origin);
bounds.extend(destination);

        L.polyline(
            [origin, destination],
            {
                color: '#ffffff',
                weight: 1,
                opacity: 0.35,
                dashArray: '5 7',
            },
        ).addTo(map!);

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
            .addTo(map!);

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
            .addTo(map!);
    });

    if (!bounds.isValid()) {
        map.setView([40.4168, -3.7038], 4);
    } else {
        map.fitBounds(bounds, {
            padding: [40, 40],
            maxZoom: 5,
        });
    }
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
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