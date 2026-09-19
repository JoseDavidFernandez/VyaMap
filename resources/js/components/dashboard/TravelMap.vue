<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';

interface MapCity {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
}

interface MapFlight {
    id: number;
    origin: {
        name: string;
        latitude: number;
        longitude: number;
    };
    destination: {
        name: string;
        latitude: number;
        longitude: number;
    };
}

const props = defineProps<{
    cities: MapCity[];
    flights: MapFlight[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: true,
        attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const points: L.LatLngExpression[] = [];

    props.cities.forEach((city) => {
        const coordinates: L.LatLngExpression = [
            city.latitude,
            city.longitude,
        ];

        points.push(coordinates);

        L.marker(coordinates)
            .addTo(map!)
            .bindPopup(`<strong>${city.name}</strong>`);
    });

    props.flights.forEach((flight) => {
        const origin: L.LatLngExpression = [
            flight.origin.latitude,
            flight.origin.longitude,
        ];

        const destination: L.LatLngExpression = [
            flight.destination.latitude,
            flight.destination.longitude,
        ];

        points.push(origin, destination);

        L.polyline([origin, destination], {
            color: '#1D2235',
            weight: 2,
            opacity: 0.7,
            dashArray: '6 8',
        }).addTo(map!);
    });

    if (points.length > 0) {
        map.fitBounds(L.latLngBounds(points), {
            padding: [40, 40],
        });
    } else {
        map.setView([40.4168, -3.7038], 5);
    }
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div
        ref="mapElement"
        class="h-[520px] w-full rounded-[var(--vyamap-radius-xl)]"
    />
</template>