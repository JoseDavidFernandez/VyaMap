<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import L from 'leaflet';
import type { FlightRecord as Flight } from '../../types/flights';
import { greatCircleRoute, toPoint, type Point } from '../../utils/greatCircle';

// Realizados en blanco, próximos en lima y pendientes de confirmar en gris.
const props = defineProps<{
    flights: Flight[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let layerGroup: L.LayerGroup | null = null;

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
        const origin = toPoint(flight.origin.latitude, flight.origin.longitude);
        const destination = toPoint(flight.destination.latitude, flight.destination.longitude);

        // Sin coordenadas válidas no se puede dibujar ni la ruta ni sus marcadores.
        if (!origin || !destination) {
            return;
        }

        const route = greatCircleRoute(origin, destination);
        const upcoming = flight.status === 'upcoming';
        // Pendiente de confirmar: gris, nunca como un vuelo realizado.
        const unconfirmed = flight.status === 'pending';
        const stroke = upcoming ? '#BEF264' : unconfirmed ? 'rgba(255, 255, 255, 0.3)' : '#ffffff';

        route.flat().forEach((point) => bounds.extend(point));

        L.polyline(
            route,
            {
                color: stroke,
                weight: 1,
                opacity: upcoming ? 0.7 : unconfirmed ? 0.25 : 0.35,
                dashArray: '5 7',
            },
        ).addTo(layerGroup!);

        L.circleMarker(origin, {
            radius: 4,
            color: stroke,
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
            color: stroke,
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
        class="flight-history-map vyamap-leaflet-dark"
    />
</template>

<style scoped>
.flight-history-map {
    width: 100%;
    height: 100%;
    min-height: 520px;
    background: #111827;
}
</style>