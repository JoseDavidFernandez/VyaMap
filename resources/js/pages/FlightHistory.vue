<script setup lang="ts">

import { computed, ref } from 'vue';

import AppLayout from '../layouts/AppLayout.vue';
import FlightHistoryMap from '../components/flight-history/FlightHistoryMap.vue';


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
    distance_km: number;
    duration_minutes: number | null;
    origin: AirportPoint;
    destination: AirportPoint;
}

interface VisitedAirport {
    code: string | null;
    city: string;
    airport: string;
    count: number;
}

const props = defineProps<{
    flights: Flight[];
}>();

const selectedPeriod = ref<number | 'all'>('all');

/*
|--------------------------------------------------------------------------
| Periods
|--------------------------------------------------------------------------
*/

const years = computed(() => {
    return Array.from(
        new Set(
            props.flights
                .filter((flight) => flight.departure)
                .map((flight) =>
                    new Date(flight.departure!).getFullYear(),
                ),
        ),
    ).sort((a, b) => b - a);
});

const filteredFlights = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.flights;
    }

    return props.flights.filter((flight) => {
        if (!flight.departure) {
            return false;
        }

        return (
            new Date(flight.departure).getFullYear() ===
            selectedPeriod.value
        );
    });
});

/*
|--------------------------------------------------------------------------
| Map
|--------------------------------------------------------------------------
*/

const mapFlights = computed(() =>
    filteredFlights.value.map((flight) => ({
        id: flight.id,
        flight_number: flight.flight_number,
        airline: flight.airline,
        departure: flight.departure,
        arrival: flight.arrival,
        origin: {
            code: flight.origin.code,
            airport: flight.origin.airport,
            city: flight.origin.city,
            latitude: flight.origin.latitude,
            longitude: flight.origin.longitude,
        },
        destination: {
            code: flight.destination.code,
            airport: flight.destination.airport,
            city: flight.destination.city,
            latitude: flight.destination.latitude,
            longitude: flight.destination.longitude,
        },
    })),
);


/*
|--------------------------------------------------------------------------
| Last flight
|--------------------------------------------------------------------------
*/

const lastFlight = computed(() => {
    return [...filteredFlights.value]
        .filter((flight) => flight.departure)
        .sort((a, b) => {
            return (
                new Date(b.departure!).getTime() -
                new Date(a.departure!).getTime()
            );
        })[0] ?? null;
});


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

const totalDistance = computed(() => {
    return filteredFlights.value.reduce(
        (total, flight) => total + (flight.distance_km || 0),
        0,
    );
});

const totalDuration = computed(() => {
    return filteredFlights.value.reduce(
        (total, flight) =>
            total + (flight.duration_minutes || 0),
        0,
    );
});

const uniqueAirports = computed(() => {
    const airports = new Set<string>();

    filteredFlights.value.forEach((flight) => {
        const origin =
            flight.origin.code ??
            `${flight.origin.city}-${flight.origin.airport}`;

        const destination =
            flight.destination.code ??
            `${flight.destination.city}-${flight.destination.airport}`;

        airports.add(origin);
        airports.add(destination);
    });

    return airports.size;
});

const uniqueCities = computed(() => {
    const cities = new Set<string>();

    filteredFlights.value.forEach((flight) => {
        cities.add(flight.origin.city);
        cities.add(flight.destination.city);
    });

    return cities.size;
});

const averageDistance = computed(() => {
    if (!filteredFlights.value.length) {
        return 0;
    }

    return Math.round(
        totalDistance.value /
            filteredFlights.value.length,
    );
});

const averageDuration = computed(() => {
    if (!filteredFlights.value.length) {
        return 0;
    }

    return Math.round(
        totalDuration.value /
            filteredFlights.value.length,
    );
});

/*
|--------------------------------------------------------------------------
| Most visited airports
|--------------------------------------------------------------------------
*/

const mostVisitedAirports = computed<VisitedAirport[]>(() => {
    const airports = new Map<string, VisitedAirport>();

    filteredFlights.value.forEach((flight) => {
        const points = [
            flight.origin,
            flight.destination,
        ];

        points.forEach((airport) => {
            const key =
                airport.code ??
                `${airport.city}-${airport.airport}`;

            const existing = airports.get(key);

            if (existing) {
                existing.count += 1;
                return;
            }

            airports.set(key, {
                code: airport.code,
                city: airport.city,
                airport: airport.airport,
                count: 1,
            });
        });
    });

    return Array.from(airports.values())
        .sort((a, b) => b.count - a.count)
        .slice(0, 5);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    ).format(new Date(date));
};

const formatTime = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        },
    ).format(new Date(date));
};

const formatDistance = (distance: number) => {
    if (distance < 1000) {
        return `${distance.toLocaleString('en-GB')} km`;
    }

    return `${new Intl.NumberFormat(
        'en-GB',
        {
            maximumFractionDigits: 1,
        },
    ).format(distance / 1000)}k km`;
};

const formatDuration = (minutes: number | null) => {
    if (
        minutes === null ||
        minutes === undefined
    ) {
        return '—';
    }

    const hours = Math.floor(minutes / 60);

    const remainingMinutes =
        minutes % 60;

    if (hours === 0) {
        return `${remainingMinutes}m`;
    }

    if (remainingMinutes === 0) {
        return `${hours}h`;
    }

    return `${hours}h ${remainingMinutes}m`;
};

const periodLabel = computed(() => {
    return selectedPeriod.value === 'all'
        ? 'All-Time'
        : String(selectedPeriod.value);
});

const flightCountLabel = computed(() => {
    return filteredFlights.value.length === 1
        ? 'flight'
        : 'flights';
});

const formattedTotalDistance = computed(() => {
    if (totalDistance.value < 1000) {
        return `${totalDistance.value.toLocaleString('en-GB')} km`;
    }

    return `${new Intl.NumberFormat(
        'en-GB',
        {
            maximumFractionDigits: 1,
        },
    ).format(totalDistance.value / 1000)}k`;
});

const formattedTotalDuration = computed(() => {
    return formatDuration(totalDuration.value);
});

const formattedAverageDistance = computed(() => {
    if (!averageDistance.value) {
        return '—';
    }

    return `${averageDistance.value.toLocaleString('en-GB')} km`;
});

const formattedAverageDuration = computed(() => {
    if (!averageDuration.value) {
        return '—';
    }

    return formatDuration(
        averageDuration.value,
    );
});

</script>

<template>
    <AppLayout title="Flight History">
        <div class="vyamap-page">

            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- =====================================================
                     HEADER
                ====================================================== -->

<section class="mb-7">
    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-6 py-9 sm:px-10 sm:py-11 lg:px-12 lg:py-12">
        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

        <div class="relative z-10">
            <div class="text-[9px] uppercase tracking-[0.24em] text-white/25">
                Travel history
            </div>

            <div class="mt-3 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <h1 class="text-5xl font-semibold tracking-[-0.06em] text-white sm:text-6xl lg:text-7xl">
                        Flight History
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm leading-6 text-white/35 sm:text-base">
                        A complete record of every flight you have added to VyaMap.
                    </p>
                </div>

                <div class="shrink-0">
                    <div class="flex gap-2 overflow-x-auto pb-1 lg:justify-end lg:pb-0">
                        <button
                            type="button"
                            class="shrink-0 rounded-full border px-4 py-2 text-xs font-medium transition"
                            :class="selectedPeriod === 'all'
                                ? 'border-white/20 bg-white/[0.08] text-white'
                                : 'border-white/[0.06] bg-white/[0.02] text-white/40 hover:bg-white/[0.05] hover:text-white/70'"
                            @click="selectedPeriod = 'all'"
                        >
                            All-Time
                        </button>

                        <button
                            v-for="year in years"
                            :key="year"
                            type="button"
                            class="shrink-0 rounded-full border px-4 py-2 text-xs font-medium transition"
                            :class="selectedPeriod === year
                                ? 'border-white/20 bg-white/[0.08] text-white'
                                : 'border-white/[0.06] bg-white/[0.02] text-white/40 hover:bg-white/[0.05] hover:text-white/70'"
                            @click="selectedPeriod = year"
                        >
                            {{ year }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

                <!-- =====================================================
                     STATISTICS
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="grid grid-cols-2 overflow-hidden rounded-[28px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] md:grid-cols-4"
                    >
                        <div
                            class="border-b border-r border-[var(--vyamap-border)] p-6 md:border-b-0"
                        >

                            <p class="vyamap-section-title">
                                Flights
                            </p>

                            <p
                                class="mt-3 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                {{ filteredFlights.length }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                {{ periodLabel }}
                            </p>

                        </div>


                        <div
                            class="border-b border-[var(--vyamap-border)] p-6 md:border-b-0 md:border-r"
                        >

                            <p class="vyamap-section-title">
                                Distance
                            </p>

                            <p
                                class="mt-3 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                {{ formattedTotalDistance }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                total flown
                            </p>

                        </div>


                        <div
                            class="border-r border-[var(--vyamap-border)] p-6"
                        >

                            <p class="vyamap-section-title">
                                Time in air
                            </p>

                            <p
                                class="mt-3 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                {{ formattedTotalDuration }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                total duration
                            </p>

                        </div>


                        <div class="p-6">

                            <p class="vyamap-section-title">
                                Airports
                            </p>

                            <p
                                class="mt-3 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                {{ uniqueAirports }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                {{ uniqueCities }} cities
                            </p>

                        </div>

                    </div>

                </section>

                <!-- =====================================================
                     SECONDARY STATISTICS
                ====================================================== -->

                <section class="mb-7">

                    <div
                        class="grid gap-7 md:grid-cols-2"
                    >

                        <div
                            class="vyamap-card-lg p-6 sm:p-7"
                        >

                            <p class="vyamap-section-title">
                                Average flight
                            </p>

                            <div
                                class="mt-5 flex items-end justify-between gap-6"
                            >

                                <div>

                                    <p
                                        class="text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        {{ formattedAverageDistance }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs vyamap-text-subtle"
                                    >
                                        distance per flight
                                    </p>

                                </div>


                                <div class="text-right">

                                    <p
                                        class="text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        {{ formattedAverageDuration }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs vyamap-text-subtle"
                                    >
                                        duration per flight
                                    </p>

                                </div>

                            </div>

                        </div>


                        <div
                            class="vyamap-card-lg p-6 sm:p-7"
                        >

                            <p class="vyamap-section-title">
                                Coverage
                            </p>

                            <div
                                class="mt-5 flex items-end justify-between gap-6"
                            >

                                <div>

                                    <p
                                        class="text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        {{ uniqueCities }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs vyamap-text-subtle"
                                    >
                                        cities connected
                                    </p>

                                </div>


                                <div class="text-right">

                                    <p
                                        class="text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        {{ uniqueAirports }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs vyamap-text-subtle"
                                    >
                                        airports used
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>

                <!-- =====================================================
                     GEOGRAPHY
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="vyamap-card-lg overflow-hidden"
                    >
                        <div
                            class="flex flex-col gap-2 px-6 pt-6 sm:px-7 sm:pt-7"
                        >
                            <div
                                class="flex items-end justify-between gap-4"
                            >
                                <div>

                                    <p class="vyamap-section-title">
                                        GEOGRAPHY
                                    </p>

                                    <h2
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        Flights map
                                    </h2>
                                </div>

                                <div
                                    class="text-right text-xs vyamap-muted"
                                >
                                    {{ filteredFlights.length }}
                                    {{ flightCountLabel }}
                                </div>

                            </div>

                        </div>


                        <div
                            class="mt-5 h-[520px] overflow-hidden"
                        >

                            <FlightHistoryMap
                                :flights="mapFlights"
                            />

                        </div>

                    </div>

                </section>

                <!-- =====================================================
                     LAST FLIGHT + MOST VISITED
                ====================================================== -->

                <section
                    class="mb-7 grid gap-7 lg:grid-cols-[1.6fr_0.9fr]"
                >

                    <!-- LAST FLIGHT -->

                    <div
                        class="relative overflow-hidden rounded-[28px] border border-white/[0.08] bg-[#090b0d]"
                    >

                        <div
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.06),transparent_42%)]"
                        ></div>


                        <div
                            class="relative flex min-h-[290px] flex-col items-center justify-center px-6 py-10 sm:px-10"
                        >

                            <p
                                class="text-[9px] uppercase tracking-[0.28em] text-white/25"
                            >
                                Most recent flight
                            </p>


                            <div
                                v-if="lastFlight"
                                class="mt-8 w-full"
                            >

                                <div
                                    class="flex items-center justify-center gap-8 sm:gap-12"
                                >

                                    <div
                                        class="min-w-0 text-center"
                                    >

                                        <div
                                            class="text-5xl font-semibold tracking-[-0.06em] sm:text-6xl"
                                        >
                                            {{
                                                lastFlight.origin.code ||
                                                '—'
                                            }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-white/30"
                                        >
                                            {{
                                                lastFlight.origin.city
                                            }}
                                        </div>

                                    </div>


                                    <div
                                        class="flex min-w-[80px] items-center gap-3"
                                    >

                                        <div
                                            class="h-px flex-1 bg-white/[0.12]"
                                        ></div>

                                        <span
                                            class="text-sm text-white/25"
                                        >
                                            →
                                        </span>

                                        <div
                                            class="h-px flex-1 bg-white/[0.12]"
                                        ></div>

                                    </div>


                                    <div
                                        class="min-w-0 text-center"
                                    >

                                        <div
                                            class="text-5xl font-semibold tracking-[-0.06em] sm:text-6xl"
                                        >
                                            {{
                                                lastFlight.destination.code ||
                                                '—'
                                            }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-white/30"
                                        >
                                            {{
                                                lastFlight.destination.city
                                            }}
                                        </div>

                                    </div>

                                </div>


                                <div
                                    class="mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-[11px] text-white/30"
                                >

                                    <span>
                                        {{
                                            lastFlight.flight_number
                                        }}
                                    </span>

                                    <span
                                        class="h-1 w-1 rounded-full bg-white/15"
                                    ></span>

                                    <span>
                                        {{
                                            lastFlight.airline ||
                                            'Flight'
                                        }}
                                    </span>

                                    <span
                                        class="h-1 w-1 rounded-full bg-white/15"
                                    ></span>

                                    <span>
                                        {{
                                            formatDate(
                                                lastFlight.departure,
                                            )
                                        }}
                                    </span>

                                    <span
                                        class="h-1 w-1 rounded-full bg-white/15"
                                    ></span>

                                    <span>
                                        {{
                                            formatDuration(
                                                lastFlight.duration_minutes,
                                            )
                                        }}
                                    </span>

                                </div>

                            </div>


                            <div
                                v-else
                                class="mt-8 text-sm text-white/25"
                            >
                                No flights recorded in this period.
                            </div>

                        </div>

                    </div>

                    <!-- MOST VISITED -->

                    <div
                        class="vyamap-card-lg p-6 sm:p-7"
                    >

                        <div class="mb-5">

                            <p class="vyamap-section-title">
                                Airports
                            </p>

                            <h2
                                class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                Most visited
                            </h2>

                        </div>


                        <div
                            v-if="mostVisitedAirports.length"
                            class="divide-y divide-[var(--vyamap-border)]"
                        >

                            <div
                                v-for="airport in mostVisitedAirports"
                                :key="
                                    airport.code ??
                                    `${airport.city}-${airport.airport}`
                                "
                                class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0"
                            >

                                <div
                                    class="flex min-w-0 items-center gap-4"
                                >

                                    <div
                                        class="w-12 shrink-0 text-sm font-semibold"
                                    >
                                        {{ airport.code || '—' }}
                                    </div>

                                    <div class="min-w-0">

                                        <div
                                            class="truncate text-sm"
                                        >
                                            {{ airport.city }}
                                        </div>

                                        <div
                                            class="mt-1 truncate text-[10px] text-white/25"
                                        >
                                            {{ airport.airport }}
                                        </div>

                                    </div>

                                </div>


                                <div
                                    class="shrink-0 text-sm text-white/35"
                                >
                                    {{ airport.count }}
                                </div>

                            </div>

                        </div>


                        <div
                            v-else
                            class="py-6 text-sm text-white/25"
                        >
                            No airport data available.
                        </div>

                    </div>

                </section>

                <!-- =====================================================
                     FLIGHT TIMELINE
                ====================================================== -->

                <section>
                    <div
                        class="vyamap-card-lg overflow-hidden"
                    >

                        <div
                            class="flex items-end justify-between gap-4 px-6 py-6 sm:px-7"
                        >
                            <div>

                                <p class="vyamap-section-title">
                                    Timeline
                                </p>

                                <h2
                                    class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                >
                                    Flights
                                </h2>

                            </div>


                            <div
                                class="text-right text-xs vyamap-muted"
                            >
                                {{ filteredFlights.length }}
                                {{ flightCountLabel }}
                            </div>

                        </div>

                        <div
                            v-if="filteredFlights.length"
                            class="hidden border-y border-[var(--vyamap-border)] px-6 py-3 text-[10px] uppercase tracking-[0.16em] vyamap-text-subtle sm:grid sm:grid-cols-[1.1fr_1.8fr_1.1fr_0.9fr_0.8fr] sm:gap-5 sm:px-7"
                        >
                            <span>
                                Flight
                            </span>

                            <span>
                                Route
                            </span>

                            <span>
                                Airline
                            </span>

                            <span>
                                Date
                            </span>

                            <span class="text-right">
                                Distance
                            </span>
                        </div>


                        <div
                            v-if="filteredFlights.length"
                            class="divide-y divide-[var(--vyamap-border)]"
                        >

                            <div
                                v-for="flight in filteredFlights"
                                :key="flight.id"
                                class="px-6 py-5 transition hover:bg-[var(--vyamap-surface-muted)] sm:px-7"
                            >

                                <div
                                    class="hidden items-center sm:grid sm:grid-cols-[1.1fr_1.8fr_1.1fr_0.9fr_0.8fr] sm:gap-5"
                                >

                                    <div class="min-w-0">

                                        <div class="truncate text-sm font-semibold" >
                                            {{
                                                flight.flight_number
                                            }}
                                        </div>

                                        <div
                                            v-if="
                                                flight.origin.code ||
                                                flight.destination.code
                                            "
                                            class="mt-1 text-[11px] vyamap-text-subtle"
                                        >
                                            {{
                                                flight.origin.code ||
                                                '—'
                                            }}
                                            →
                                            {{
                                                flight.destination.code ||
                                                '—'
                                            }}
                                        </div>

                                    </div>


                                    <div class="min-w-0">

                                        <div
                                            class="truncate text-sm"
                                        >
                                            {{
                                                flight.origin.city
                                            }}

                                            <span
                                                class="mx-2 vyamap-text-subtle"
                                            >
                                                →
                                            </span>

                                            {{
                                                flight.destination.city
                                            }}
                                        </div>

                                    </div>


                                    <div
                                        class="truncate text-sm vyamap-muted"
                                    >
                                        {{
                                            flight.airline ||
                                            '—'
                                        }}
                                    </div>


                                    <div
                                        class="text-xs vyamap-muted"
                                    >
                                        {{
                                            formatDate(
                                                flight.departure,
                                            )
                                        }}
                                    </div>


                                    <div
                                        class="text-right text-xs vyamap-muted"
                                    >
                                        {{
                                            formatDistance(
                                                flight.distance_km,
                                            )
                                        }}
                                    </div>

                                </div>


                                <div class="sm:hidden">

                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >

                                        <div class="min-w-0">

                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >

                                                <span
                                                    class="text-sm font-semibold"
                                                >
                                                    {{
                                                        flight.flight_number
                                                    }}
                                                </span>

                                                <span
                                                    class="text-xs vyamap-text-subtle"
                                                >
                                                    {{
                                                        flight.airline ||
                                                        '—'
                                                    }}
                                                </span>

                                            </div>


                                            <div
                                                class="mt-2 text-sm"
                                            >
                                                {{
                                                    flight.origin.city
                                                }}

                                                <span
                                                    class="mx-2 vyamap-text-subtle"
                                                >
                                                    →
                                                </span>

                                                {{
                                                    flight.destination.city
                                                }}
                                            </div>


                                            <div
                                                class="mt-1 text-[11px] vyamap-text-subtle"
                                            >
                                                {{
                                                    flight.origin.code ||
                                                    '—'
                                                }}
                                                →
                                                {{
                                                    flight.destination.code ||
                                                    '—'
                                                }}
                                            </div>

                                        </div>


                                        <div
                                            class="shrink-0 text-right"
                                        >

                                            <div
                                                class="text-xs vyamap-muted"
                                            >
                                                {{
                                                    formatDate(
                                                        flight.departure,
                                                    )
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 text-xs vyamap-text-subtle"
                                            >
                                                {{
                                                    formatDistance(
                                                        flight.distance_km,
                                                    )
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 text-xs vyamap-text-subtle"
                                            >
                                                {{
                                                    formatDuration(
                                                        flight.duration_minutes,
                                                    )
                                                }}
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <div
                            v-else
                            class="border-t border-[var(--vyamap-border)] px-6 py-8 sm:px-7"
                        >

                            <p
                                class="text-sm vyamap-text-subtle"
                            >
                                No flights recorded in this period.
                            </p>

                        </div>

                    </div>

                </section>

            </main>

        </div>

    </AppLayout>
</template>