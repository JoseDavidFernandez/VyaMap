<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';

interface MapFlight {
    id: number;

    flight_number: string;

    airline: string | null;

    departure: string | null;

    arrival: string | null;

    origin: {
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };

    destination: {
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };
}

const props = defineProps<{
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
        worldCopyJump: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const points: L.LatLngExpression[] = [];

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

        /*
        |--------------------------------------------------------------------------
        | Flight route
        |--------------------------------------------------------------------------
        */

        L.polyline([origin, destination], {
            color: '#1D2235',
            weight: 2,
            opacity: 0.7,
            dashArray: '6 8',
        })
            .addTo(map!)
            .bindTooltip(
                `${flight.flight_number} · ${flight.origin.city} → ${flight.destination.city}`,
            );

        /*
        |--------------------------------------------------------------------------
        | Airport markers
        |--------------------------------------------------------------------------
        |
        | These are airport endpoints of the flight.
        | They are NOT visited-city markers.
        |
        */

        L.circleMarker(origin, {
            radius: 4,
            color: '#1D2235',
            fillColor: '#FFFFFF',
            fillOpacity: 1,
            weight: 2,
        })
            .addTo(map!)
            .bindTooltip(flight.origin.city);

        L.circleMarker(destination, {
            radius: 4,
            color: '#1D2235',
            fillColor: '#FFFFFF',
            fillOpacity: 1,
            weight: 2,
        })
            .addTo(map!)
            .bindTooltip(flight.destination.city);
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