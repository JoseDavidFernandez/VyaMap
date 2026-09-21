<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import L from 'leaflet';

interface Visit {
    id: number;
    city: {
        id: number;
        name: string;
        country: string;
        iso_code: string;
        latitude: number | null;
        longitude: number | null;
    };
}

interface Flight {
    id: number;
    flight_number: string;
    airline: string | null;
    origin: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };
    destination: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };
}

const props = defineProps<{
    visits: Visit[];
    flights: Flight[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let layers: L.Layer[] = [];

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
    });

    L.control.zoom({
        position: 'topright',
    }).addTo(map);

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution: '&copy; OpenStreetMap contributors',
        },
    ).addTo(map);

    const bounds = L.latLngBounds([]);

    /*
     * Visited cities
     */
    props.visits.forEach((visit) => {
        const { latitude, longitude } = visit.city;

        if (latitude === null || longitude === null) {
            return;
        }

        const marker = L.circleMarker([latitude, longitude], {
            radius: 8,
            color: '#1D2235',
            weight: 2,
            fillColor: '#FFFFFF',
            fillOpacity: 1,
        })
            .bindTooltip(
                `<strong>${visit.city.name}</strong><br>${visit.city.country}`,
                {
                    direction: 'top',
                    offset: [0, -8],
                },
            )
            .addTo(map!);

        layers.push(marker);

        bounds.extend([latitude, longitude]);
    });

    /*
     * Flights
     */
    props.flights.forEach((flight) => {
        const origin: L.LatLngExpression = [
            flight.origin.latitude,
            flight.origin.longitude,
        ];

        const destination: L.LatLngExpression = [
            flight.destination.latitude,
            flight.destination.longitude,
        ];

        /*
         * Origin airport
         */
        const originMarker = L.circleMarker(origin, {
            radius: 5,
            color: '#1D2235',
            weight: 1.5,
            fillColor: '#1D2235',
            fillOpacity: 1,
        })
            .bindTooltip(
                `<strong>${flight.origin.city}</strong><br>Departure airport`,
                {
                    direction: 'top',
                    offset: [0, -6],
                },
            )
            .addTo(map!);

        layers.push(originMarker);

        /*
         * Destination airport
         */
        const destinationMarker = L.circleMarker(destination, {
            radius: 5,
            color: '#1D2235',
            weight: 1.5,
            fillColor: '#1D2235',
            fillOpacity: 1,
        })
            .bindTooltip(
                `<strong>${flight.destination.city}</strong><br>Arrival airport`,
                {
                    direction: 'top',
                    offset: [0, -6],
                },
            )
            .addTo(map!);

        layers.push(destinationMarker);

        /*
         * Flight route
         */
        const line = L.polyline([origin, destination], {
            color: '#1D2235',
            weight: 2,
            opacity: 0.65,
            dashArray: '6 8',
        })
            .bindTooltip(
                `<strong>${flight.flight_number}</strong>${flight.airline ? `<br>${flight.airline}` : ''}`,
                {
                    sticky: true,
                },
            )
            .addTo(map!);

        layers.push(line);

        bounds.extend(origin);
        bounds.extend(destination);
    });

    /*
     * Fit map around trip data.
     */
    if (!bounds.isValid()) {
        map.setView([20, 0], 2);
        return;
    }

    map.fitBounds(bounds, {
        padding: [50, 50],
        maxZoom: 6,
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
    <div
        ref="mapElement"
        class="h-[420px] w-full"
    />
</template>