<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

import AppLayout from '../layouts/AppLayout.vue';
import FlightHistoryMap from '../components/flight-history/FlightHistoryMap.vue';
import FlightForm from '../components/flights/FlightForm.vue';
import { calendarDate, flightDepartureLabel } from '../utils/flightTime';
import { calendarYear } from '../utils/tripDates';
import type { FlightRecord as Flight, FlightStatus, TripOption } from '../types/flights';
import type { FlightStats } from '../types/statistics';

const props = defineProps<{
    flights: Flight[];
    trips: TripOption[];
    // Cifras del servidor por combinación de filtros: "periodo:vínculo".
    stats: Record<string, FlightStats>;
}>();

const selectedPeriod = ref<number | 'all'>('all');
const selectedStatus = ref<'all' | Exclude<FlightStatus, 'cancelled'>>('all');
const selectedLink = ref<'all' | 'trip' | 'standalone'>('all');

/*
|--------------------------------------------------------------------------
| Periods
|--------------------------------------------------------------------------
*/

// Año de la fecha local de salida (siempre conocida, con o sin hora).
const years = computed(() =>
    Array.from(new Set(props.flights.map((flight) => calendarYear(flight.departure_date)!)))
        .sort((a, b) => b - a),
);

// Año y viaje: base de las cifras (que solo cuentan los realizados).
const periodFlights = computed(() =>
    props.flights.filter((flight) =>
        (selectedPeriod.value === 'all' || calendarYear(flight.departure_date) === selectedPeriod.value)
        && (selectedLink.value === 'all' || (selectedLink.value === 'trip') === Boolean(flight.trip))),
);

// Lista y mapa: además, el estado elegido.
const filteredFlights = computed(() =>
    periodFlights.value.filter((flight) => selectedStatus.value === 'all' || flight.status === selectedStatus.value),
);

// Las cifras solo cuentan vuelos realizados.
const flownFlights = computed(() =>
    periodFlights.value.filter((flight) => flight.status === 'flown'),
);

const statusCounts = computed(() => ({
    upcoming: periodFlights.value.filter((flight) => flight.status === 'upcoming').length,
    pending: periodFlights.value.filter((flight) => flight.status === 'pending').length,
}));

/*
|--------------------------------------------------------------------------
| Map
|--------------------------------------------------------------------------
*/

const mapFlights = computed(() => filteredFlights.value);


/*
|--------------------------------------------------------------------------
| Last flight
|--------------------------------------------------------------------------
*/

// El servidor ya los envía del más reciente al más antiguo (fecha local, hora, id).
const lastFlight = computed(() => flownFlights.value[0] ?? null);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

// Cifras de los vuelos realizados del periodo y vínculo elegidos, calculadas en
// el servidor (TravelStatistics). Distancia y tiempo son null cuando ningún
// vuelo tiene el dato: se muestran como desconocidos, nunca como cero.
const periodStats = computed<FlightStats | null>(() =>
    props.stats[`${selectedPeriod.value}:${selectedLink.value}`] ?? null,
);

const flownCount = computed(() => periodStats.value?.flights ?? 0);
const totalDistance = computed(() => periodStats.value?.distance_km ?? null);
const totalDuration = computed(() => periodStats.value?.air_minutes ?? null);
const uniqueAirports = computed(() => periodStats.value?.airports ?? 0);
const uniqueCities = computed(() => periodStats.value?.cities ?? 0);
const mostVisitedAirports = computed(() => periodStats.value?.top_airports ?? []);
const longestFlight = computed(() => periodStats.value?.longest ?? null);

// "5 of 7 flights with times" cuando falta el dato en alguno.
const durationCoverage = computed(() =>
    periodStats.value && periodStats.value.flights_without_times > 0
        ? `${periodStats.value.flights_with_times} of ${periodStats.value.flights} flights with times`
        : '',
);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Management
|--------------------------------------------------------------------------
*/

const showForm = ref(false);
const editingFlight = ref<Flight | null>(null);
const formKey = ref(0);

const openForm = (flight: Flight | null = null) => {
    editingFlight.value = flight;
    formKey.value += 1;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingFlight.value = null;
};

const routeLabel = (flight: Flight) =>
    `${flight.origin.code ?? flight.origin.city ?? flight.origin.airport} → ${flight.destination.code ?? flight.destination.city ?? flight.destination.airport}`;

const deleteFlight = (flight: Flight) => {
    if (!window.confirm(`Delete the flight ${routeLabel(flight)} on ${calendarDate(flight.departure_date)}? This cannot be undone.`)) {
        return;
    }

    router.delete(`/flights/${flight.id}`, { preserveScroll: true });
};

// Confirmar que un vuelo se realizó (o deshacerlo). No toca el viaje.
const setConfirmed = (flight: Flight, confirmed: boolean) => {
    router.patch(`/flights/${flight.id}/confirmation`, { confirmed }, { preserveScroll: true });
};

const statusBadge = (flight: Flight) => ({
    upcoming: { label: 'Upcoming', class: 'border-lime-300/40 text-lime-300' },
    pending: { label: 'Unconfirmed', class: 'border-white/20 text-white/50' },
    flown: { label: 'Flown', class: 'border-cyan-300/30 text-cyan-200' },
    cancelled: { label: 'Cancelled', class: 'border-white/15 text-white/40' },
})[flight.status];

const formatDistance = (distance: number | null) => {
    if (distance === null) {
        return '—';
    }

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
    return flownCount.value === 1
        ? 'flight'
        : 'flights';
});

const formattedTotalDistance = computed(() => {
    if (totalDistance.value === null) {
        return '—';
    }

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

const formattedTotalDuration = computed(() =>
    totalDuration.value === null ? '—' : formatDuration(totalDuration.value),
);

const formattedAverageDistance = computed(() => {
    const average = periodStats.value?.average_distance_km ?? null;

    return average === null ? '—' : `${average.toLocaleString('en-GB')} km`;
});

const formattedAverageDuration = computed(() => {
    const average = periodStats.value?.average_air_minutes ?? null;

    return average === null ? '—' : formatDuration(average);
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
                    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12">
                        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

                        <div class="relative z-10">
                            <div class="text-[9px] uppercase tracking-[0.24em] text-white/25">
                                Travel history
                            </div>

                            <div class="mt-3 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                                <div class="min-w-0">
                                    <h1 class="text-4xl font-semibold tracking-[-0.06em] text-white sm:text-6xl lg:text-7xl">
                                        Flight History
                                    </h1>

                                    <p class="mt-3 max-w-2xl text-xs leading-5 text-white/35 sm:mt-4 sm:text-base sm:leading-6">
                                        A complete record of every flight you have added to VyaMap.
                                    </p>
                                </div>

                                <div class="shrink-0">
                                    <div class="grid grid-cols-2 gap-2 sm:flex sm:overflow-x-auto sm:pb-1 lg:justify-end lg:pb-0">
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-full border px-3 py-2 text-[11px] font-medium transition sm:px-4 sm:text-xs"
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
                                            class="shrink-0 rounded-full border px-3 py-2 text-[11px] font-medium transition sm:px-4 sm:text-xs"
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
                     MANAGEMENT: añadir vuelos y filtros por estado y viaje
                ====================================================== -->

                <section class="mb-7 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <button
                        type="button"
                        class="vyamap-button w-full sm:w-auto"
                        @click="openForm()"
                    >
                        + Add flight
                    </button>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in ([
                                { value: 'all', label: 'All' },
                                { value: 'flown', label: 'Flown' },
                                { value: 'upcoming', label: `Upcoming${statusCounts.upcoming ? ` · ${statusCounts.upcoming}` : ''}` },
                                { value: 'pending', label: `Unconfirmed${statusCounts.pending ? ` · ${statusCounts.pending}` : ''}` },
                            ] as const)"
                            :key="option.value"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="selectedStatus === option.value
                                ? 'border-white/20 bg-white/[0.08] text-white'
                                : 'border-white/[0.06] text-white/45 hover:text-white/80'"
                            :aria-pressed="selectedStatus === option.value"
                            @click="selectedStatus = option.value"
                        >
                            {{ option.label }}
                        </button>

                        <span class="mx-1 hidden h-7 w-px bg-white/[0.08] sm:block" />

                        <button
                            v-for="option in ([
                                { value: 'all', label: 'All flights' },
                                { value: 'trip', label: 'In trips' },
                                { value: 'standalone', label: 'Standalone' },
                            ] as const)"
                            :key="option.value"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                            :class="selectedLink === option.value
                                ? 'border-white/20 bg-white/[0.08] text-white'
                                : 'border-white/[0.06] text-white/45 hover:text-white/80'"
                            :aria-pressed="selectedLink === option.value"
                            @click="selectedLink = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </section>

                <section
                    v-if="showForm"
                    class="vyamap-card-lg mb-7 p-6 sm:p-8"
                >
                    <h2 class="mb-6 text-xl font-semibold tracking-[-0.04em]">
                        {{ editingFlight ? 'Edit flight' : 'Add flight' }}
                    </h2>

                    <FlightForm
                        :key="formKey"
                        :flight="editingFlight"
                        :trips="trips"
                        @close="closeForm"
                    />
                </section>

                <!-- =====================================================
                     STATISTICS
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="grid grid-cols-2 overflow-hidden rounded-[28px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] md:grid-cols-4"
                    >
                        <div
                            class="border-b border-r border-[var(--vyamap-border)] p-4 sm:p-6 md:border-b-0"
                        >

                            <p class="vyamap-section-title">
                                Flights
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold tracking-[-0.05em] sm:mt-3 sm:text-3xl"
                            >
                                {{ flownCount }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                {{ periodLabel }}
                            </p>

                        </div>


                        <div
                            class="border-b border-[var(--vyamap-border)] p-4 sm:p-6 md:border-b-0 md:border-r"
                        >

                            <p class="vyamap-section-title">
                                Distance
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold tracking-[-0.05em] sm:mt-3 sm:text-3xl"
                            >
                                {{ formattedTotalDistance }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                total flown
                            </p>

                            <p
                                v-if="longestFlight"
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                Longest: {{ longestFlight.origin || '—' }} → {{ longestFlight.destination || '—' }}
                                · {{ longestFlight.distance_km.toLocaleString('en-GB') }} km
                            </p>

                        </div>


                        <div
                            class="border-r border-[var(--vyamap-border)] p-4 sm:p-6"
                        >

                            <p class="vyamap-section-title">
                                Time in air
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold tracking-[-0.05em] sm:mt-3 sm:text-3xl"
                            >
                                {{ formattedTotalDuration }}
                            </p>

                            <p
                                class="mt-1 text-xs vyamap-text-subtle"
                            >
                                {{ durationCoverage || 'total duration' }}
                            </p>

                        </div>


                        <div class="p-4 sm:p-6">

                            <p class="vyamap-section-title">
                                Airports
                            </p>

                            <p
                                class="mt-2 text-2xl font-semibold tracking-[-0.05em] sm:mt-3 sm:text-3xl"
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
                            class="vyamap-card-lg p-5 sm:p-7"
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
                            class="vyamap-card-lg p-5 sm:p-7"
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
                                        class="mt-1 text-2xl font-semibold tracking-[-0.05em] sm:mt-2 sm:text-3xl"
                                    >
                                        Flights map
                                    </h2>
                                </div>

                                <div
                                    class="text-right text-xs vyamap-muted"
                                >
                                    {{ flownCount }}
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
                            class="relative flex min-h-[240px] flex-col items-center justify-center px-4 py-7 sm:min-h-[290px] sm:px-10 sm:py-10"
                        >

                            <p
                                class="text-[9px] uppercase tracking-[0.28em] text-white/25"
                            >
                                Most recent flight
                            </p>


                            <div
                                v-if="lastFlight"
                                class="mt-6 w-full sm:mt-8"
                            >

                                <div
                                    class="flex items-center justify-center gap-4 sm:gap-12"
                                >

                                    <div
                                        class="min-w-0 text-center"
                                    >

                                        <div
                                            class="text-3xl font-semibold tracking-[-0.06em] sm:text-6xl"
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
                                        class="flex min-w-[50px] items-center gap-2 sm:min-w-[80px] sm:gap-3"
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
                                            class="text-3xl font-semibold tracking-[-0.06em] sm:text-6xl"
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
                                    class="mt-6 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-[10px] text-white/30 sm:mt-8 sm:gap-x-5 sm:text-[11px]"
                                >

                                    <span>
                                        {{
                                            lastFlight.flight_number || 'Flight'
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
                                            flightDepartureLabel(lastFlight)
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
                        class="vyamap-card-lg p-5 sm:p-7"
                    >

                        <div class="mb-4 sm:mb-5">

                            <p class="vyamap-section-title">
                                Airports
                            </p>

                            <h2
                                class="mt-1 text-2xl font-semibold tracking-[-0.05em] sm:mt-2 sm:text-3xl"
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
                                :key="airport.id"
                                class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0 sm:gap-4 sm:py-4"
                            >

                                <div
                                    class="flex min-w-0 items-center gap-3 sm:gap-4"
                                >

                                    <div
                                        class="w-10 shrink-0 text-xs font-semibold sm:w-12 sm:text-sm"
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
                                    class="mt-1 text-2xl font-semibold tracking-[-0.05em] sm:mt-2 sm:text-3xl"
                                >
                                    Flights
                                </h2>

                            </div>


                            <div
                                class="text-right text-xs vyamap-muted"
                            >
                                {{ flownCount }}
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
                                            {{ flight.flight_number || 'Flight' }}
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
                                            flightDepartureLabel(flight)
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
                                                    {{ flight.flight_number || 'Flight' }}
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
                                                    flightDepartureLabel(flight)
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

                                <!-- Estado, viaje y acciones -->
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                        <span
                                            class="rounded-full border px-2 py-0.5 font-medium"
                                            :class="statusBadge(flight).class"
                                        >
                                            {{ statusBadge(flight).label }}
                                        </span>

                                        <Link
                                            v-if="flight.trip"
                                            :href="`/trips/${flight.trip.id}`"
                                            class="text-white/55 underline-offset-4 hover:text-white hover:underline"
                                        >
                                            {{ flight.trip.name }}
                                        </Link>

                                        <span v-else class="text-white/40">Standalone</span>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="flight.status === 'pending'"
                                            type="button"
                                            class="rounded-full bg-cyan-300 px-3 py-1 text-[11px] font-semibold text-[#0d1119] transition hover:bg-cyan-200"
                                            @click="setConfirmed(flight, true)"
                                        >
                                            Confirm flown
                                        </button>

                                        <button
                                            v-if="flight.confirmed"
                                            type="button"
                                            class="rounded-full border border-white/[0.08] px-3 py-1 text-[11px] text-white/50 transition hover:text-white"
                                            @click="setConfirmed(flight, false)"
                                        >
                                            Undo confirmation
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-full border border-white/[0.08] px-3 py-1 text-[11px] text-white/50 transition hover:text-white"
                                            @click="openForm(flight)"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-full border border-red-400/[0.15] px-3 py-1 text-[11px] text-red-200/60 transition hover:text-red-100"
                                            @click="deleteFlight(flight)"
                                        >
                                            Delete
                                        </button>
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