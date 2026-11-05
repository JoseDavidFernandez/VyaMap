<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

import AppLayout from '../layouts/AppLayout.vue';
import Icon from '../components/ui/Icon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FlightHistoryMap from '../components/flight-history/FlightHistoryMap.vue';
import FlightForm from '../components/flights/FlightForm.vue';
import { calendarDate, flightDepartureLabel } from '../utils/flightTime';
import { calendarYear } from '../utils/tripDates';
import type { FlightRecord as Flight, FlightStatus, TripOption } from '../types/flights';
import type { FlightStats } from '../types/statistics';
import { tripPath } from '../utils/routes';
import { stockImage } from '../data/stockImages';

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
    upcoming: { label: 'Upcoming', class: 'vyamap-badge-violet' },
    pending: { label: 'Unconfirmed', class: 'vyamap-badge-amber' },
    flown: { label: 'Flown', class: 'vyamap-badge-cyan' },
    cancelled: { label: 'Cancelled', class: '' },
})[flight.status];

const filtersActive = computed(() =>
    selectedPeriod.value !== 'all' || selectedStatus.value !== 'all' || selectedLink.value !== 'all',
);

const clearFilters = () => {
    selectedPeriod.value = 'all';
    selectedStatus.value = 'all';
    selectedLink.value = 'all';
};

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
        <div class="vyamap-container vyamap-stack">
            <header class="vyamap-page-header !mb-0">
                <div>
                    <h1 class="vyamap-page-title">Flight History</h1>
                    <p class="vyamap-page-description">A complete record of every flight you have added to VyaMap.</p>
                </div>
                <button v-if="!showForm" type="button" class="vyamap-button" @click="openForm()">
                    <Icon name="plus" :size="16" />
                    Add flight
                </button>
            </header>

            <!-- FORMULARIO (compartido con el detalle del viaje) -->
            <section v-if="showForm" class="vyamap-panel border-[rgba(34,211,238,0.35)]">
                <h2 class="vyamap-h2 mb-5">{{ editingFlight ? 'Edit flight' : 'Add flight' }}</h2>
                <FlightForm :key="formKey" :flight="editingFlight" :trips="trips" @close="closeForm" />
            </section>

            <EmptyState
                v-if="!flights.length && !showForm"
                icon="flight"
                :image="stockImage('wing-above-clouds')"
                title="No flights yet"
                text="Add a flight with its airports and date. Times are optional; with them VyaMap calculates time in the air. Flights can belong to a trip or stand alone."
            >
                <template #actions>
                    <button type="button" class="vyamap-button" @click="openForm()">
                        <Icon name="plus" :size="16" />
                        Add your first flight
                    </button>
                </template>
            </EmptyState>

            <template v-if="flights.length">
                <!-- FILTROS -->
                <section class="flex flex-wrap items-center gap-2" aria-label="Filters">
                    <div class="vyamap-segmented" role="group" aria-label="Period">
                        <button type="button" class="vyamap-segment" :aria-pressed="selectedPeriod === 'all'" @click="selectedPeriod = 'all'">
                            All-Time
                        </button>
                        <button
                            v-for="year in years"
                            :key="year"
                            type="button"
                            class="vyamap-segment vyamap-number"
                            :aria-pressed="selectedPeriod === year"
                            @click="selectedPeriod = year"
                        >
                            {{ year }}
                        </button>
                    </div>

                    <div class="vyamap-segmented" role="group" aria-label="Status">
                        <button
                            v-for="option in ([
                                { value: 'all', label: 'All' },
                                { value: 'flown', label: 'Flown' },
                                { value: 'upcoming', label: `Upcoming${statusCounts.upcoming ? ` · ${statusCounts.upcoming}` : ''}` },
                                { value: 'pending', label: `Unconfirmed${statusCounts.pending ? ` · ${statusCounts.pending}` : ''}` },
                            ] as const)"
                            :key="option.value"
                            type="button"
                            class="vyamap-segment"
                            :aria-pressed="selectedStatus === option.value"
                            @click="selectedStatus = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <div class="vyamap-segmented" role="group" aria-label="Trip">
                        <button
                            v-for="option in ([
                                { value: 'all', label: 'All flights' },
                                { value: 'trip', label: 'In trips' },
                                { value: 'standalone', label: 'Standalone' },
                            ] as const)"
                            :key="option.value"
                            type="button"
                            class="vyamap-segment"
                            :aria-pressed="selectedLink === option.value"
                            @click="selectedLink = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <button v-if="filtersActive" type="button" class="vyamap-button-ghost vyamap-button-sm" @click="clearFilters">
                        <Icon name="close" :size="16" />
                        Clear filters
                    </button>
                </section>

                <!-- CIFRAS (solo vuelos realizados) -->
                <section class="vyamap-card overflow-hidden" aria-label="Flight statistics">
                    <dl class="grid grid-cols-2 lg:grid-cols-4">
                        <div class="vyamap-stat p-4 sm:p-5">
                            <dt class="vyamap-stat-label order-2">Flights flown</dt>
                            <dd class="vyamap-stat-value order-1">{{ flownCount }}</dd>
                            <dd class="vyamap-stat-hint order-3">{{ periodLabel }}</dd>
                        </div>
                        <div class="vyamap-stat border-l border-[var(--vyamap-border)] p-4 sm:p-5">
                            <dt class="vyamap-stat-label order-2">Distance</dt>
                            <dd class="vyamap-stat-value order-1">{{ formattedTotalDistance }}<span v-if="totalDistance !== null && totalDistance >= 1000" class="vyamap-stat-unit">km</span></dd>
                            <dd v-if="longestFlight" class="vyamap-stat-hint order-3 vyamap-number">
                                Longest: {{ longestFlight.origin || '—' }} → {{ longestFlight.destination || '—' }} · {{ longestFlight.distance_km.toLocaleString('en-GB') }} km
                            </dd>
                        </div>
                        <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-4 sm:p-5 lg:border-l lg:border-t-0">
                            <dt class="vyamap-stat-label order-2">Time in air</dt>
                            <dd class="vyamap-stat-value order-1">{{ formattedTotalDuration }}</dd>
                            <dd v-if="durationCoverage" class="vyamap-stat-hint order-3">{{ durationCoverage }}</dd>
                        </div>
                        <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-4 sm:p-5 lg:border-t-0">
                            <dt class="vyamap-stat-label order-2">Airports</dt>
                            <dd class="vyamap-stat-value order-1">{{ uniqueAirports }}</dd>
                            <dd class="vyamap-stat-hint order-3">{{ uniqueCities }} {{ uniqueCities === 1 ? 'city' : 'cities' }} connected</dd>
                        </div>
                    </dl>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 border-t border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] px-5 py-3 text-sm text-[var(--vyamap-text-muted)]">
                        <span>Average flight</span>
                        <span><strong class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ formattedAverageDistance }}</strong> per flight</span>
                        <span><strong class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ formattedAverageDuration }}</strong> in the air</span>
                    </div>
                </section>

                <!-- MAPA -->
                <section class="vyamap-map-card h-[340px] sm:h-[480px]">
                    <div class="vyamap-map-overlay left-4 top-4">
                        <h2 class="text-sm font-semibold">Flights map</h2>
                        <p class="vyamap-number text-xs text-[var(--vyamap-text-muted)]">
                            {{ filteredFlights.length }} {{ filteredFlights.length === 1 ? 'flight' : 'flights' }} shown
                        </p>
                    </div>
                    <div class="vyamap-map-overlay bottom-4 left-4 hidden gap-4 text-xs sm:flex">
                        <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-[var(--vyamap-cyan-soft)]" />Flown</span>
                        <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-[var(--vyamap-violet)]" />Upcoming</span>
                        <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-[var(--vyamap-text-subtle)]" />Unconfirmed</span>
                    </div>
                    <FlightHistoryMap :flights="mapFlights" />
                </section>

                <!-- ÚLTIMO VUELO Y AEROPUERTOS -->
                <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <div class="vyamap-panel flex flex-col">
                        <h2 class="vyamap-h2">Most recent flight</h2>

                        <div v-if="lastFlight" class="flex flex-1 flex-col justify-center py-6">
                            <div class="flex items-center gap-4 sm:gap-6">
                                <div class="min-w-0 text-center">
                                    <p class="vyamap-code text-[clamp(2rem,1.4rem+3vw,3.5rem)] leading-none">{{ lastFlight.origin.code || '—' }}</p>
                                    <p class="mt-2 truncate text-sm text-[var(--vyamap-text-muted)]">{{ lastFlight.origin.city }}</p>
                                </div>
                                <span class="vyamap-route-line" aria-hidden="true" />
                                <div class="min-w-0 text-center">
                                    <p class="vyamap-code text-[clamp(2rem,1.4rem+3vw,3.5rem)] leading-none">{{ lastFlight.destination.code || '—' }}</p>
                                    <p class="mt-2 truncate text-sm text-[var(--vyamap-text-muted)]">{{ lastFlight.destination.city }}</p>
                                </div>
                            </div>
                            <p class="mt-6 flex flex-wrap justify-center gap-x-3 gap-y-1 text-sm text-[var(--vyamap-text-muted)]">
                                <span class="vyamap-code text-[var(--vyamap-text)]">{{ lastFlight.flight_number || 'Flight' }}</span>
                                <span v-if="lastFlight.airline">· {{ lastFlight.airline }}</span>
                                <span class="vyamap-number">· {{ flightDepartureLabel(lastFlight) }}</span>
                                <span v-if="lastFlight.duration_minutes" class="vyamap-number">· {{ formatDuration(lastFlight.duration_minutes) }}</span>
                            </p>
                        </div>

                        <p v-else class="py-6 text-sm text-[var(--vyamap-text-muted)]">No flights flown in this period.</p>
                    </div>

                    <div class="vyamap-panel">
                        <h2 class="vyamap-h2 mb-3">Most used airports</h2>
                        <ol v-if="mostVisitedAirports.length" class="flex flex-col divide-y divide-[var(--vyamap-border)]">
                            <li v-for="airport in mostVisitedAirports" :key="airport.id" class="flex items-center gap-3 py-2.5">
                                <span class="vyamap-code w-12 shrink-0 text-sm text-[var(--vyamap-cyan-soft)]">{{ airport.code || '—' }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ airport.city }}</span>
                                    <span class="block truncate text-[0.8125rem] text-[var(--vyamap-text-subtle)]">{{ airport.airport }}</span>
                                </span>
                                <span class="vyamap-number shrink-0 text-sm text-[var(--vyamap-text-muted)]">{{ airport.count }}</span>
                            </li>
                        </ol>
                        <p v-else class="text-sm text-[var(--vyamap-text-muted)]">No airport data for this period.</p>
                    </div>
                </section>

                <!-- LISTADO -->
                <section class="vyamap-card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 px-5 py-4">
                        <h2 class="vyamap-h2">Flights</h2>
                        <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">
                            {{ filteredFlights.length }} {{ filteredFlights.length === 1 ? 'flight' : 'flights' }}
                        </span>
                    </div>

                    <div
                        v-if="filteredFlights.length"
                        class="hidden border-y border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] px-5 py-2.5 text-[0.75rem] font-semibold uppercase tracking-[0.05em] text-[var(--vyamap-text-subtle)] md:grid md:grid-cols-[1fr_1.7fr_1fr_1.2fr_0.7fr] md:gap-4"
                        aria-hidden="true"
                    >
                        <span>Flight</span>
                        <span>Route</span>
                        <span>Airline</span>
                        <span>Date</span>
                        <span class="text-right">Distance</span>
                    </div>

                    <ul v-if="filteredFlights.length" class="divide-y divide-[var(--vyamap-border)]">
                        <li v-for="flight in filteredFlights" :key="flight.id" class="px-5 py-4 transition-colors hover:bg-[var(--vyamap-surface-muted)]">
                            <!-- Escritorio -->
                            <div class="hidden items-center md:grid md:grid-cols-[1fr_1.7fr_1fr_1.2fr_0.7fr] md:gap-4">
                                <div class="min-w-0">
                                    <p class="vyamap-code truncate text-sm">{{ flight.flight_number || 'Flight' }}</p>
                                    <p v-if="flight.origin.code || flight.destination.code" class="vyamap-code text-[0.75rem] !font-medium text-[var(--vyamap-text-subtle)]">
                                        {{ flight.origin.code || '—' }} – {{ flight.destination.code || '—' }}
                                    </p>
                                </div>
                                <div class="flex min-w-0 items-center gap-3 text-sm">
                                    <span class="truncate">{{ flight.origin.city }}</span>
                                    <span class="vyamap-route-line max-w-16" aria-hidden="true" />
                                    <span class="truncate">{{ flight.destination.city }}</span>
                                </div>
                                <p class="truncate text-sm text-[var(--vyamap-text-muted)]">{{ flight.airline || '—' }}</p>
                                <p class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ flightDepartureLabel(flight) }}</p>
                                <p class="vyamap-number text-right text-sm text-[var(--vyamap-text-muted)]">{{ formatDistance(flight.distance_km) }}</p>
                            </div>

                            <!-- Móvil: la ruta en su propia línea a todo el ancho; fecha y distancia, debajo. -->
                            <div class="md:hidden">
                                <p class="flex min-w-0 items-center gap-2">
                                    <span class="vyamap-code text-sm">{{ flight.flight_number || 'Flight' }}</span>
                                    <span v-if="flight.airline" class="truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">{{ flight.airline }}</span>
                                </p>
                                <p class="mt-1 flex min-w-0 items-center gap-2 text-sm">
                                    <span class="min-w-0 truncate">{{ flight.origin.city }}</span>
                                    <span class="vyamap-route-line max-w-10" aria-hidden="true" />
                                    <span class="min-w-0 truncate">{{ flight.destination.city }}</span>
                                </p>
                                <p class="vyamap-number mt-1 text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                    {{ flightDepartureLabel(flight) }} · {{ formatDistance(flight.distance_km) }}<template v-if="flight.duration_minutes"> · {{ formatDuration(flight.duration_minutes) }}</template>
                                </p>
                            </div>

                            <!-- Estado, viaje y acciones -->
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2 text-[0.8125rem]">
                                    <span class="vyamap-badge" :class="statusBadge(flight).class">{{ statusBadge(flight).label }}</span>
                                    <Link v-if="flight.trip" :href="tripPath(flight.trip)" class="vyamap-link inline-flex items-center gap-1">
                                        <Icon name="trips" :size="14" />
                                        {{ flight.trip.name }}
                                    </Link>
                                    <span v-else class="text-[var(--vyamap-text-subtle)]">Standalone</span>
                                </div>

                                <div class="flex flex-wrap gap-1">
                                    <button v-if="flight.status === 'pending'" type="button" class="vyamap-button vyamap-button-sm" @click="setConfirmed(flight, true)">
                                        <Icon name="check" :size="16" />
                                        Confirm flown
                                    </button>
                                    <button v-if="flight.confirmed" type="button" class="vyamap-button-ghost vyamap-button-sm" @click="setConfirmed(flight, false)">
                                        Undo confirmation
                                    </button>
                                    <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="openForm(flight)">
                                        <Icon name="edit" :size="16" />
                                        Edit
                                    </button>
                                    <button type="button" class="vyamap-button-ghost vyamap-button-sm hover:!text-[var(--vyamap-danger)]" @click="deleteFlight(flight)">
                                        <Icon name="trash" :size="16" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="border-t border-[var(--vyamap-border)] p-5">
                        <EmptyState compact icon="search" title="No flights match these filters" text="Try another year, status or trip filter.">
                            <template #actions>
                                <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="clearFilters">Clear filters</button>
                            </template>
                        </EmptyState>
                    </div>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
