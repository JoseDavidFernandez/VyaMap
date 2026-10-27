<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';
import { greatCircleRoute, toPoint, type Point } from '../../utils/greatCircle';
import { escapeHtml } from '../../utils/escapeHtml';

interface MapFlight {
    id: number;

    // flown o upcoming (los próximos se dibujan en violeta).
    status?: 'flown' | 'upcoming' | 'pending';

    flight_number: string | null;

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
const UPCOMING = '#A78BFA';

const airportMarker = (point: Point, city: string, upcoming = false) =>
    L.circleMarker(point, {
        radius: 4,
        color: upcoming ? UPCOMING : '#5EE7F7',
        weight: 1.5,
        fillColor: '#070B14',
        fillOpacity: 1,
    }).bindTooltip(escapeHtml(city), {
        direction: 'top',
        offset: [0, -4],
        className: 'vyamap-tooltip',
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
        const origin = toPoint(flight.origin.latitude, flight.origin.longitude);
        const destination = toPoint(flight.destination.latitude, flight.destination.longitude);

        // Sin coordenadas válidas no se puede dibujar ni la ruta ni sus marcadores.
        if (!origin || !destination) {
            return;
        }

        const route = greatCircleRoute(origin, destination);

        route.flat().forEach((point) => bounds.extend(point));

        /*
        |--------------------------------------------------------------------------
        | Flight route
        |--------------------------------------------------------------------------
        */

        const upcoming = flight.status === 'upcoming';

        L.polyline(route, {
            color: upcoming ? UPCOMING : '#5EE7F7',
            weight: 1,
            opacity: upcoming ? 0.7 : 0.35,
            dashArray: '5 7',
        })
            .addTo(map!)
            .bindTooltip(
                `${flight.flight_number ? `${escapeHtml(flight.flight_number)} · ` : ''}${escapeHtml(flight.origin.city)} → ${escapeHtml(flight.destination.city)}${upcoming ? ' · upcoming' : ''}`,
                { sticky: true, className: 'vyamap-tooltip' },
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

        airportMarker(origin, flight.origin.city, upcoming).addTo(map!);
        airportMarker(destination, flight.destination.city, upcoming).addTo(map!);
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
    background: #0A0F1B;
}
</style>
