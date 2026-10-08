<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import FlightHistoryMap from '../../components/trips/FlightHistoryMap.vue';
import { computed } from 'vue';

interface Airport {
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
    origin: Airport;
    destination: Airport;
}

interface Trip {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

const props = defineProps<{
    trip: Trip;
    flights: Flight[];
}>();

const totalFlights = computed(() => props.flights.length);

const totalAirports = computed(() => {
    const airports = new Set<number>();

    props.flights.forEach((flight) => {
        airports.add(flight.origin.id);
        airports.add(flight.destination.id);
    });

    return airports.size;
});

const totalRoutes = computed(() => {
    const routes = new Set<string>();

    props.flights.forEach((flight) => {
        routes.add(
            `${flight.origin.id}-${flight.destination.id}`,
        );
    });

    return routes.size;
});

const totalDistance = computed(() => {
    let distance = 0;

    props.flights.forEach((flight) => {
        distance += calculateDistance(
            flight.origin.latitude,
            flight.origin.longitude,
            flight.destination.latitude,
            flight.destination.longitude,
        );
    });

    return Math.round(distance);
});

const formatDistance = (distance: number) => {
    if (distance >= 1000) {
        return `${(distance / 1000).toFixed(1)}k`;
    }

    return `${distance}`;
};

const calculateDistance = (
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number,
) => {
    const earthRadius = 6371;

    const lat1Rad = (lat1 * Math.PI) / 180;
    const lat2Rad = (lat2 * Math.PI) / 180;

    const deltaLat =
        ((lat2 - lat1) * Math.PI) / 180;

    const deltaLon =
        ((lon2 - lon1) * Math.PI) / 180;

    const a =
        Math.sin(deltaLat / 2) *
            Math.sin(deltaLat / 2) +
        Math.cos(lat1Rad) *
            Math.cos(lat2Rad) *
            Math.sin(deltaLon / 2) *
            Math.sin(deltaLon / 2);

    const c =
        2 *
        Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a),
        );

    return earthRadius * c;
};

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};

const formatTime = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(date));
};

const formatDuration = (
    departure: string | null,
    arrival: string | null,
) => {
    if (!departure || !arrival) {
        return '—';
    }

    const start = new Date(departure).getTime();
    const end = new Date(arrival).getTime();

    const minutes = Math.round(
        (end - start) / 60000,
    );

    if (minutes < 60) {
        return `${minutes}m`;
    }

    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;

    if (remaining === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${remaining}m`;
};

const latestFlight = computed(() => {
    if (!props.flights.length) {
        return null;
    }

    return [...props.flights].sort((a, b) => {
        const aDate = a.departure
            ? new Date(a.departure).getTime()
            : 0;

        const bDate = b.departure
            ? new Date(b.departure).getTime()
            : 0;

        return bDate - aDate;
    })[0];
});

const routeLabel = (flight: Flight) => {
    return `${flight.origin.city} → ${flight.destination.city}`;
};
</script>

<template>
    <AppLayout>
        <div class="min-h-screen bg-[#f7f7f5] text-[#1d2235]">
            <main class="mx-auto max-w-[1500px] px-6 py-8 lg:px-10 lg:py-12">

                <!-- HEADER -->

                <header class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="mb-3 text-[10px] font-semibold uppercase tracking-[0.22em] text-[#1d2235]/40">
                            VyaMap / Flight history
                        </div>

                        <h1 class="text-4xl font-semibold tracking-[-0.04em] sm:text-5xl lg:text-6xl">
                            Flight history
                        </h1>

                        <p class="mt-4 max-w-xl text-sm leading-6 text-[#1d2235]/50">
                            Every flight recorded in VyaMap.
                            A visual archive of the routes, airports
                            and journeys that connect your travels.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="rounded-full border border-[#1d2235]/10 bg-white px-4 py-2 text-[11px] font-medium">
                            {{ trip.name }}
                        </div>

                        <div class="rounded-full border border-[#1d2235]/10 bg-white px-4 py-2 text-[11px] text-[#1d2235]/50">
                            {{ trip.start_date || '—' }}
                        </div>
                    </div>
                </header>


                <!-- STATS -->

                <section class="mt-12 grid grid-cols-2 border-y border-[#1d2235]/10 sm:grid-cols-4">

                    <div class="border-r border-[#1d2235]/10 px-4 py-6 sm:px-6">
                        <div class="text-3xl font-semibold tracking-[-0.04em]">
                            {{ totalFlights }}
                        </div>

                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Flights
                        </div>
                    </div>

                    <div class="px-4 py-6 sm:border-r sm:border-[#1d2235]/10 sm:px-6">
                        <div class="text-3xl font-semibold tracking-[-0.04em]">
                            {{ formatDistance(totalDistance) }}
                            <span class="text-sm font-normal text-[#1d2235]/40">
                                km
                            </span>
                        </div>

                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Distance
                        </div>
                    </div>

                    <div class="border-r border-t border-[#1d2235]/10 px-4 py-6 sm:border-t-0 sm:px-6">
                        <div class="text-3xl font-semibold tracking-[-0.04em]">
                            {{ totalAirports }}
                        </div>

                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Airports
                        </div>
                    </div>

                    <div class="border-t border-[#1d2235]/10 px-4 py-6 sm:border-t-0 sm:px-6">
                        <div class="text-3xl font-semibold tracking-[-0.04em]">
                            {{ totalRoutes }}
                        </div>

                        <div class="mt-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Routes
                        </div>
                    </div>

                </section>


                <!-- FEATURED FLIGHT -->

                <section
                    v-if="latestFlight"
                    class="mt-12 grid overflow-hidden rounded-[2px] border border-[#1d2235]/10 bg-white lg:grid-cols-[1fr_0.38fr]"
                >

                    <div class="relative min-h-[500px]">
                        <FlightHistoryMap
                            :flights="flights"
                        />
                    </div>

                    <div class="flex flex-col justify-between border-t border-[#1d2235]/10 p-7 lg:border-l lg:border-t-0 lg:p-9">

                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/40">
                                Latest flight
                            </div>

                            <div class="mt-8">
                                <div class="text-5xl font-semibold tracking-[-0.06em]">
                                    {{ latestFlight.origin.city }}
                                </div>

                                <div class="my-4 flex items-center gap-3">
                                    <div class="h-px flex-1 bg-[#1d2235]/15"></div>

                                    <div class="text-xs text-[#1d2235]/40">
                                        →
                                    </div>

                                    <div class="h-px flex-1 bg-[#1d2235]/15"></div>
                                </div>

                                <div class="text-5xl font-semibold tracking-[-0.06em]">
                                    {{ latestFlight.destination.city }}
                                </div>
                            </div>
                        </div>


                        <div class="mt-12 border-t border-[#1d2235]/10 pt-6">

                            <div class="grid grid-cols-2 gap-y-6">

                                <div>
                                    <div class="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/35">
                                        Flight
                                    </div>

                                    <div class="mt-1 text-sm font-medium">
                                        {{ latestFlight.flight_number }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/35">
                                        Airline
                                    </div>

                                    <div class="mt-1 text-sm font-medium">
                                        {{ latestFlight.airline || '—' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/35">
                                        Date
                                    </div>

                                    <div class="mt-1 text-sm font-medium">
                                        {{ formatDate(latestFlight.departure) }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/35">
                                        Duration
                                    </div>

                                    <div class="mt-1 text-sm font-medium">
                                        {{
                                            formatDuration(
                                                latestFlight.departure,
                                                latestFlight.arrival,
                                            )
                                        }}
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>
                </section>


                <!-- EMPTY STATE -->

                <section
                    v-else
                    class="mt-12 flex min-h-[500px] items-center justify-center border border-dashed border-[#1d2235]/15 bg-white"
                >
                    <div class="text-center">
                        <div class="text-lg font-medium">
                            No flights recorded
                        </div>

                        <p class="mt-2 text-sm text-[#1d2235]/45">
                            Add flights to this trip to see them here.
                        </p>
                    </div>
                </section>


                <!-- FLIGHT LIST -->

                <section
                    v-if="flights.length"
                    class="mt-16"
                >

                    <div class="mb-6 flex items-end justify-between">
                        <div>
                            <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/40">
                                Archive
                            </div>

                            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">
                                All flights
                            </h2>
                        </div>

                        <div class="text-[11px] text-[#1d2235]/40">
                            {{ flights.length }}
                            {{ flights.length === 1 ? 'flight' : 'flights' }}
                        </div>
                    </div>


                    <div class="overflow-hidden border-y border-[#1d2235]/10">

                        <article
                            v-for="(flight, index) in flights"
                            :key="flight.id"
                            class="group grid grid-cols-[42px_1fr] border-b border-[#1d2235]/10 py-6 last:border-b-0 lg:grid-cols-[60px_1.5fr_1fr_0.7fr_0.7fr]"
                        >

                            <div class="hidden text-[10px] font-medium text-[#1d2235]/25 lg:block">
                                {{ String(index + 1).padStart(2, '0') }}
                            </div>

                            <div class="pl-0 lg:pl-0">
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">
                                        {{ flight.origin.city }}
                                    </span>

                                    <span class="text-xs text-[#1d2235]/30">
                                        →
                                    </span>

                                    <span class="text-sm font-semibold">
                                        {{ flight.destination.city }}
                                    </span>
                                </div>

                                <div class="mt-1 text-[11px] text-[#1d2235]/40">
                                    {{ flight.origin.name }}
                                    →
                                    {{ flight.destination.name }}
                                </div>
                            </div>

                            <div class="mt-4 lg:mt-0">
                                <div class="text-xs font-medium">
                                    {{ flight.flight_number }}
                                </div>

                                <div class="mt-1 text-[11px] text-[#1d2235]/40">
                                    {{ flight.airline || 'Unknown airline' }}
                                </div>
                            </div>

                            <div class="mt-4 lg:mt-0">
                                <div class="text-[9px] font-semibold uppercase tracking-[0.14em] text-[#1d2235]/30">
                                    Date
                                </div>

                                <div class="mt-1 text-xs">
                                    {{ formatDate(flight.departure) }}
                                </div>
                            </div>

                            <div class="mt-4 lg:mt-0">
                                <div class="text-[9px] font-semibold uppercase tracking-[0.14em] text-[#1d2235]/30">
                                    Duration
                                </div>

                                <div class="mt-1 text-xs">
                                    {{
                                        formatDuration(
                                            flight.departure,
                                            flight.arrival,
                                        )
                                    }}
                                </div>
                            </div>

                        </article>

                    </div>
                </section>


                <!-- ROUTES -->

                <section
                    v-if="flights.length"
                    class="mt-16 pb-16"
                >
                    <div class="mb-6">
                        <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/40">
                            Network
                        </div>

                        <h2 class="mt-2 text-2xl font-semibold tracking-[-0.03em]">
                            Routes
                        </h2>
                    </div>

                    <div class="grid gap-px overflow-hidden border border-[#1d2235]/10 bg-[#1d2235]/10 sm:grid-cols-2 lg:grid-cols-3">

                        <div
                            v-for="flight in flights"
                            :key="`route-${flight.id}`"
                            class="bg-[#f7f7f5] p-6"
                        >
                            <div class="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/35">
                                {{ flight.flight_number }}
                            </div>

                            <div class="mt-4 text-lg font-semibold tracking-[-0.03em]">
                                {{ routeLabel(flight) }}
                            </div>

                            <div class="mt-2 text-[11px] text-[#1d2235]/40">
                                {{
                                    formatDistance(
                                        calculateDistance(
                                            flight.origin.latitude,
                                            flight.origin.longitude,
                                            flight.destination.latitude,
                                            flight.destination.longitude,
                                        ),
                                    )
                                }}
                                km
                            </div>
                        </div>

                    </div>
                </section>

            </main>
        </div>
    </AppLayout>
</template>