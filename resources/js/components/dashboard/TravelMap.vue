<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';
import { greatCircleRoute, isValidPoint, type Point } from '../../utils/greatCircle';

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

/*
 * Misma representación que el mapa de /flight-history (FlightHistoryMap):
 * arcos de círculo máximo, líneas finas y el mismo marcador de aeropuerto.
 */
const airportMarker = (point: Point, city: string) =>
    L.circleMarker(point, {
        radius: 4,
        color: '#ffffff',
        weight: 1.5,
        fillColor: '#111827',
        fillOpacity: 1,
    }).bindTooltip(city, {
        direction: 'top',
        offset: [0, -4],
    });

onMounted(() => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        worldCopyJump: true,
    });

    L.control
        .zoom({
            position: 'bottomright',
        })
        .addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

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

        const route = greatCircleRoute(origin, destination);

        route.flat().forEach((point) => bounds.extend(point));

        /*
        |--------------------------------------------------------------------------
        | Flight route
        |--------------------------------------------------------------------------
        */

        L.polyline(route, {
            color: '#ffffff',
            weight: 1,
            opacity: 0.35,
            dashArray: '5 7',
        })
            .addTo(map!)
            .bindTooltip(
                `${flight.flight_number} · ${flight.origin.city} → ${flight.destination.city}`,
                { sticky: true },
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

        airportMarker(origin, flight.origin.city).addTo(map!);
        airportMarker(destination, flight.destination.city).addTo(map!);
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds, {
            padding: [40, 40],
            maxZoom: 5,
        });
    } else {
        map.setView([40.4168, -3.7038], 4);
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
        class="travel-map vyamap-leaflet-dark"
    />
</template>

<style scoped>
.travel-map {
    width: 100%;
    height: 100%;
    background: #111827;
}
</style>
