<script setup lang="ts">

import AppLayout from '../layouts/AppLayout.vue';
import CountryMap from '../components/dashboard/CountryMap.vue';
import TravelMap from '../components/dashboard/TravelMap.vue';

import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '../components/ui/Icon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import { flightDate, flightDepartureLabel, flightTimeWithZone } from '../utils/flightTime';
import type { CountryStatus } from '../utils/countryStatus';
import { countryPath, tripPath } from '../utils/routes';
import StockImage from '../components/ui/StockImage.vue';
import { stockImage, stockImageFor } from '../data/stockImages';

interface User {
    name: string;
}

interface Stats {
    countries: number;
    sovereign_countries: number;
    total_sovereign_countries: number;
    territories: number;
    cities: number;
    trips: number;
    upcoming_trips: number;
    flights: number;
    // null si ningún vuelo realizado tiene coordenadas (desconocido, no cero).
    flight_km: number | null;
}

interface ThisYear {
    year: number;
    days: number;
    countries: number;
    new_countries: { iso_code: string; name: string }[];
}

interface PlannedCountry {
    iso_code: string;
    name: string;
    ongoing: boolean;
}

interface PendingTrip {
    id: number;
    name: string;
    end_date: string | null;
}

interface NextTrip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    days_until: number;
}

interface Visit {
    id: number;
    trip_id: number;
    city: {
        id: number;
        name: string;
        country: string;
        country_id: number;
        country_slug: string;
        iso_code: string;
        country_status: CountryStatus;
        latitude: number | null;
        longitude: number | null;
    };
    visited_from: string | null;
    visited_until: string | null;
    notes: string | null;
}

interface Flight {
    id: number;
    // Fecha local de salida (siempre); horas y número, solo si se conocen.
    departure_date: string;
    flight_number: string | null;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    status?: 'flown' | 'upcoming' | 'pending';
    origin: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
        timezone: string | null;
    };
    destination: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
        timezone: string | null;
    };
}

interface RecentTrip {
    id: number;
    phase: 'completed' | 'ongoing' | 'pending';
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    cities: string[];
    countries: string[];
    cover: string | null;
}

interface Photo {
    id: number;
    // URLs autenticadas (PhotoFileController); nunca rutas de disco.
    url: string;
    thumbnail_url: string;
    original_filename: string;
}

interface TripsByYear {
    year: number;
    count: number;
}

const props = defineProps<{
    user: User;
    stats: Stats;
    thisYear: ThisYear;
    nextTrip: NextTrip | null;
    pendingTrips: PendingTrip[];
    map: {
        visits: Visit[];
        flights: Flight[];
        planned_countries: PlannedCountry[];
    };
    recentFlights: Flight[];
    recentTrips: RecentTrip[];
    recentPhotos: Photo[];
    tripsByYear: TripsByYear[];
}>();

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
};

const formatDateShort = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
    }).format(new Date(`${date}T00:00:00`));
};

// Hora local del aeropuerto, con su zona (CET, GMT+9…).
const formatFlightDateTime = (date: string | null, timezone: string | null) =>
    date ? `${flightDate(date, timezone, false)}, ${flightTimeWithZone(date, timezone)}` : '—';

const nextTripLabel = computed(() => {
    if (!props.nextTrip) {
        return 'No upcoming trips';
    }
    if (props.nextTrip.days_until === 0) {
        return 'Today';
    }
    if (props.nextTrip.days_until === 1) {
        return 'Tomorrow';
    }

    return `In ${props.nextTrip.days_until} days`;
});

/*
|--------------------------------------------------------------------------
| Countries
|--------------------------------------------------------------------------
*/

const visitedCountries = computed(() => {
    const countries = new Map<
        string,
        {
            id: number;
            name: string;
            iso_code: string;
        }
    >();

    props.map.visits.forEach((visit) => {
        if (!countries.has(visit.city.iso_code)) {
            countries.set(visit.city.iso_code, {
                id: visit.city.country_id,
                name: visit.city.country,
                iso_code: visit.city.iso_code,
            });
        }
    });

    return Array.from(countries.values());
});

/*
|--------------------------------------------------------------------------
| Countries overview
|--------------------------------------------------------------------------
*/

// El porcentaje solo considera Estados soberanos (config/countries.php).
const sovereignShare = computed(() =>
    props.stats.total_sovereign_countries
        ? (props.stats.sovereign_countries / props.stats.total_sovereign_countries) * 100
        : 0,
);

const topCountries = computed(() => {
    const countryMap = new Map<
        string,
        {
            id: number;
            slug: string;
            name: string;
            iso_code: string;
            cities: string[];
            tripIds: Set<number>;
        }
    >();

    props.map.visits.forEach((visit) => {
        const iso = visit.city.iso_code;
        if (!iso) return;

        if (!countryMap.has(iso)) {
            countryMap.set(iso, {
                id: visit.city.country_id,
                slug: visit.city.country_slug,
                name: visit.city.country,
                iso_code: iso,
                cities: [],
                tripIds: new Set(),
            });
        }

        const country = countryMap.get(iso)!;
        // Viajes distintos con al menos una visita en el país (no visitas).
        country.tripIds.add(visit.trip_id);

        if (!country.cities.includes(visit.city.name)) {
            country.cities.push(visit.city.name);
        }
    });

    return Array.from(countryMap.values())
        .map(({ tripIds, ...country }) => ({ ...country, trips: tripIds.size }))
        .sort((a, b) => {
            if (b.trips !== a.trips) return b.trips - a.trips;
            return b.cities.length - a.cities.length;
        })
        .slice(0, 3);
});

const countryFlag = (iso: string) => {
    if (!iso || iso.length !== 2) return '';

    return iso
        .toUpperCase()
        .split('')
        .map((letter) => String.fromCodePoint(127397 + letter.charCodeAt(0)))
        .join('');
};

const tripCoverUrl = (trip: RecentTrip) => {
    if (!trip.cover) return null;

    return trip.cover.startsWith('http://') ||
        trip.cover.startsWith('https://') ||
        trip.cover.startsWith('/')
        ? trip.cover
        : `/storage/${trip.cover}`;
};

const countriesPercentage = computed(() => Math.round(sovereignShare.value * 10) / 10);

/*
|--------------------------------------------------------------------------
| Trips by year
|--------------------------------------------------------------------------
*/

const maxTripsByYear = computed(() => {
    return Math.max(
        1,
        ...props.tripsByYear.map((item) => item.count),
    );
});

const isFirstRun = computed(() =>
    !props.recentTrips.length && !props.nextTrip && !props.map.visits.length && !props.recentFlights.length,
);

const uploadsEnabled = usePage().props.features.uploads;

</script>

<template>
    <AppLayout title="Home">
        <div class="vyamap-container vyamap-stack">
            <!-- Viajes cuya fecha de fin pasó y siguen planificados: nunca se
                 completan solos; el usuario los confirma desde el viaje. -->
            <section
                v-if="pendingTrips.length"
                class="flex flex-wrap items-center gap-3 rounded-[var(--vyamap-radius-lg)] border border-[rgba(251,191,36,0.35)] bg-[rgba(251,191,36,0.07)] px-4 py-3"
                role="status"
            >
                <Icon name="clock" :size="20" class="text-[var(--vyamap-warning)]" />
                <p class="text-sm font-medium text-[var(--vyamap-text)]">
                    {{ pendingTrips.length === 1 ? 'A trip is waiting for confirmation' : `${pendingTrips.length} trips are waiting for confirmation` }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="trip in pendingTrips"
                        :key="trip.id"
                        :href="tripPath(trip)"
                        class="vyamap-badge vyamap-badge-amber vyamap-badge-plain hover:bg-[rgba(251,191,36,0.18)]"
                    >
                        {{ trip.name }}
                        <Icon name="arrow-right" :size="14" />
                    </Link>
                </div>
            </section>

            <!-- CABECERA Y PRÓXIMO VIAJE -->
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)] lg:items-stretch">
                <div class="flex flex-col justify-end py-2">
                    <h1 class="vyamap-page-title">Your travel history, in one place.</h1>
                    <p class="vyamap-page-description">
                        Build your travel history, keep your memories together and see how far you have travelled.
                    </p>
                </div>

                <Link
                    v-if="nextTrip"
                    :href="tripPath(nextTrip)"
                    class="vyamap-card group flex flex-col gap-4 overflow-hidden p-5 transition-colors hover:border-[rgba(167,139,250,0.5)]"
                >
                    <div class="-mx-5 -mt-5 h-24">
                        <StockImage :image="stockImageFor(nextTrip.id)" sizes="24rem" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-medium text-[var(--vyamap-text-muted)]">Next trip</span>
                        <span class="vyamap-badge vyamap-badge-violet">{{ nextTripLabel }}</span>
                    </div>
                    <div>
                        <p class="text-2xl font-semibold tracking-[-0.03em] text-[var(--vyamap-text)]">
                            {{ nextTrip.name }}
                        </p>
                        <p class="mt-1 flex items-center gap-2 text-sm text-[var(--vyamap-text-muted)]">
                            <Icon name="calendar" :size="16" />
                            <span class="vyamap-number">
                                {{ formatDate(nextTrip.start_date) }}<template v-if="nextTrip.end_date"> – {{ formatDate(nextTrip.end_date) }}</template>
                            </span>
                        </p>
                    </div>
                    <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--vyamap-violet)]">
                        View trip
                        <Icon name="arrow-right" :size="16" class="transition-transform group-hover:translate-x-0.5" />
                    </span>
                </Link>

                <div v-else class="vyamap-card flex flex-col gap-3 p-5">
                    <span class="text-sm font-medium text-[var(--vyamap-text-muted)]">Next trip</span>
                    <p class="text-xl font-semibold tracking-[-0.02em]">Nothing planned yet</p>
                    <p class="text-sm text-[var(--vyamap-text-muted)]">Your next adventure will appear here.</p>
                    <Link href="/trips/create" class="vyamap-button-secondary mt-auto w-fit">
                        <Icon name="plus" :size="16" />
                        Plan a trip
                    </Link>
                </div>
            </section>

            <!-- PRIMERA VEZ -->
            <EmptyState
                v-if="isFirstRun"
                icon="route"
                :image="stockImage('open-road')"
                title="Start your map"
                text="Add a trip with the cities you visited and the flights you took. Your countries, distances and statistics build themselves from there."
            >
                <template #actions>
                    <Link href="/trips/create" class="vyamap-button">
                        <Icon name="plus" :size="16" />
                        New trip
                    </Link>
                    <Link href="/flight-history" class="vyamap-button-secondary">
                        <Icon name="flight" :size="16" />
                        Add a flight
                    </Link>
                </template>
            </EmptyState>

            <!-- CIFRAS -->
            <section class="vyamap-card overflow-hidden" aria-label="Travel statistics">
                <dl class="grid grid-cols-2 divide-[var(--vyamap-border)] lg:grid-cols-4 lg:divide-x">
                    <!-- Países: Estados soberanos sobre 193; los territorios, aparte. -->
                    <div class="vyamap-stat p-5">
                        <dt class="vyamap-stat-label order-2">Countries</dt>
                        <dd class="vyamap-stat-value order-1">
                            {{ stats.sovereign_countries }}<span class="vyamap-stat-unit">/ {{ stats.total_sovereign_countries }}</span>
                        </dd>
                        <dd v-if="stats.territories" class="vyamap-stat-hint order-3">
                            + {{ stats.territories }} {{ stats.territories === 1 ? 'territory' : 'territories' }}
                        </dd>
                    </div>
                    <div class="vyamap-stat border-l border-[var(--vyamap-border)] p-5 lg:border-l-0">
                        <dt class="vyamap-stat-label order-2">Cities</dt>
                        <dd class="vyamap-stat-value order-1">{{ stats.cities }}</dd>
                    </div>
                    <!-- Viajes completados; los próximos, aparte (no son historial). -->
                    <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-5 lg:border-t-0">
                        <dt class="vyamap-stat-label order-2">Trips</dt>
                        <dd class="vyamap-stat-value order-1">{{ stats.trips }}</dd>
                        <dd v-if="stats.upcoming_trips" class="vyamap-stat-hint order-3 !text-[var(--vyamap-violet)]">
                            + {{ stats.upcoming_trips }} upcoming
                        </dd>
                    </div>
                    <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-5 lg:border-l-0 lg:border-t-0">
                        <dt class="vyamap-stat-label order-2">Flights</dt>
                        <dd class="vyamap-stat-value order-1">{{ stats.flights }}</dd>
                        <dd v-if="stats.flight_km !== null" class="vyamap-stat-hint order-3 vyamap-number">
                            {{ stats.flight_km.toLocaleString('en-GB') }} km flown
                        </dd>
                    </div>
                </dl>

                <!-- Este año: días viajando (sin duplicar solapes) y países nuevos, del servidor. -->
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-[var(--vyamap-border)] bg-[var(--vyamap-surface-muted)] px-5 py-3 text-sm">
                    <span class="font-semibold text-[var(--vyamap-text)]">{{ thisYear.year }}</span>
                    <span class="text-[var(--vyamap-text-muted)]">
                        <strong class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ thisYear.days }}</strong>
                        {{ thisYear.days === 1 ? 'day' : 'days' }} traveling
                    </span>
                    <span class="text-[var(--vyamap-text-muted)]">
                        <strong class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ thisYear.new_countries.length }}</strong>
                        new {{ thisYear.new_countries.length === 1 ? 'country' : 'countries' }}
                        <template v-if="thisYear.new_countries.length">
                            ({{ thisYear.new_countries.map((country) => country.name).join(', ') }})
                        </template>
                    </span>
                    <Link href="/passport" class="vyamap-link ml-auto inline-flex items-center gap-1 text-sm">
                        All statistics
                        <Icon name="arrow-right" :size="14" />
                    </Link>
                </div>
            </section>

            <!-- MUNDO Y PAÍSES -->
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="vyamap-map-card">
                    <div class="vyamap-map-overlay left-4 top-4">
                        <h2 class="text-sm font-semibold">Your world</h2>
                        <p class="vyamap-number text-xs text-[var(--vyamap-text-muted)]">
                            {{ stats.sovereign_countries }} of {{ stats.total_sovereign_countries }} countries
                        </p>
                    </div>
                    <div class="h-[320px] w-full sm:h-[440px]">
                        <CountryMap
                            :countries="visitedCountries"
                            :planned="map.planned_countries"
                            theme="dark"
                        />
                    </div>
                </div>

                <div class="vyamap-panel flex flex-col">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Top countries</h2>
                        <Link href="/countries" class="vyamap-link text-sm">View all</Link>
                    </div>

                    <ul v-if="topCountries.length" class="-mx-2 flex flex-col">
                        <li v-for="country in topCountries" :key="country.iso_code">
                            <Link
                                :href="countryPath(country)"
                                class="vyamap-row vyamap-row-link group flex items-center gap-3 px-2 py-2.5"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--vyamap-radius-sm)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] text-lg" aria-hidden="true">
                                    {{ countryFlag(country.iso_code) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ country.name }}</span>
                                    <span class="block truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                        {{ country.cities.slice(0, 3).join(', ') }}<template v-if="country.cities.length > 3"> +{{ country.cities.length - 3 }}</template>
                                    </span>
                                </span>
                                <span class="vyamap-number shrink-0 text-[0.8125rem] text-[var(--vyamap-text-subtle)]">
                                    {{ country.trips }} {{ country.trips === 1 ? 'trip' : 'trips' }}
                                </span>
                            </Link>
                        </li>
                    </ul>

                    <EmptyState
                        v-else
                        compact
                        icon="globe"
                        title="No countries yet"
                        text="Countries appear when a completed trip has a visited city."
                    />

                    <div class="mt-auto pt-5">
                        <div class="mb-2 flex items-baseline justify-between text-sm">
                            <span class="text-[var(--vyamap-text-muted)]">World explored</span>
                            <span class="vyamap-number font-semibold">{{ countriesPercentage }}%</span>
                        </div>
                        <div
                            class="vyamap-progress"
                            role="progressbar"
                            :aria-valuenow="stats.sovereign_countries"
                            aria-valuemin="0"
                            :aria-valuemax="stats.total_sovereign_countries"
                            aria-label="Sovereign countries visited"
                        >
                            <span :style="{ width: `${Math.max(countriesPercentage, stats.sovereign_countries ? 1 : 0)}%` }" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- VIAJES RECIENTES Y POR AÑO -->
            <section v-if="!isFirstRun" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="vyamap-panel">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Recent trips</h2>
                        <Link href="/trips" class="vyamap-link text-sm">View all</Link>
                    </div>

                    <ul v-if="recentTrips.length" class="-mx-2 flex flex-col">
                        <li v-for="trip in recentTrips.slice(0, 4)" :key="trip.id">
                            <Link
                                :href="tripPath(trip)"
                                class="vyamap-row vyamap-row-link group flex items-center gap-4 px-2 py-3"
                            >
                                <span class="relative h-14 w-14 shrink-0 overflow-hidden rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)]">
                                    <img
                                        v-if="tripCoverUrl(trip)"
                                        :src="tripCoverUrl(trip)!"
                                        alt=""
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                    <span v-else class="vyamap-trip-thumb">
                                        <Icon name="pin" :size="20" />
                                    </span>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <span class="truncate text-[0.9375rem] font-semibold">{{ trip.name }}</span>
                                        <span
                                            v-if="trip.phase !== 'completed'"
                                            class="vyamap-badge"
                                            :class="trip.phase === 'ongoing' ? 'vyamap-badge-cyan' : 'vyamap-badge-amber'"
                                        >
                                            {{ trip.phase === 'ongoing' ? 'In progress' : 'Pending confirmation' }}
                                        </span>
                                    </span>
                                    <span class="mt-0.5 block truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                        <span class="vyamap-number">{{ formatDate(trip.start_date) }}<template v-if="trip.end_date"> – {{ formatDate(trip.end_date) }}</template></span>
                                        <template v-if="trip.cities.length"> · {{ trip.cities.slice(0, 3).join(', ') }}<template v-if="trip.cities.length > 3"> +{{ trip.cities.length - 3 }}</template></template>
                                    </span>
                                </span>

                                <span class="hidden shrink-0 text-[0.8125rem] text-[var(--vyamap-text-subtle)] sm:block">
                                    {{ trip.countries.join(', ') }}
                                </span>
                                <Icon name="chevron-right" :size="18" class="shrink-0 text-[var(--vyamap-text-subtle)] group-hover:text-[var(--vyamap-text)]" />
                            </Link>
                        </li>
                    </ul>

                    <EmptyState
                        v-else
                        compact
                        icon="trips"
                        title="No trips recorded yet"
                        text="Completed trips show up here with their dates and cities."
                    >
                        <template #actions>
                            <Link href="/trips/create" class="vyamap-button vyamap-button-sm">
                                <Icon name="plus" :size="16" />
                                New trip
                            </Link>
                        </template>
                    </EmptyState>
                </div>

                <div class="vyamap-panel">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Trips by year</h2>
                    </div>

                    <ul v-if="tripsByYear.length" class="flex flex-col gap-4">
                        <li v-for="item in tripsByYear" :key="item.year">
                            <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                <span class="vyamap-number font-semibold">{{ item.year }}</span>
                                <span class="vyamap-number text-[var(--vyamap-text-muted)]">
                                    {{ item.count }} {{ item.count === 1 ? 'trip' : 'trips' }}
                                </span>
                            </div>
                            <div class="vyamap-progress">
                                <span :style="{ width: `${(item.count / maxTripsByYear) * 100}%` }" />
                            </div>
                        </li>
                    </ul>

                    <p v-else class="text-sm text-[var(--vyamap-text-muted)]">
                        Each year with a completed trip gets its own bar.
                    </p>
                </div>
            </section>

            <!-- VUELOS -->
            <section v-if="!isFirstRun" class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <div class="vyamap-panel">
                    <div class="vyamap-panel-header">
                        <h2 class="vyamap-h2">Recent flights</h2>
                        <Link href="/flight-history" class="vyamap-link text-sm">Full history</Link>
                    </div>

                    <ul v-if="recentFlights.length" class="flex flex-col gap-2.5">
                        <li v-for="flight in recentFlights" :key="flight.id" class="vyamap-flight">
                            <div class="flex items-center justify-between gap-3">
                                <span class="vyamap-code text-sm">{{ flight.flight_number || 'Flight' }}</span>
                                <span v-if="flight.airline" class="truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">{{ flight.airline }}</span>
                            </div>
                            <div class="mt-2 flex items-center gap-3 text-sm">
                                <span class="truncate font-medium">{{ flight.origin.city }}</span>
                                <span class="vyamap-route-line" aria-hidden="true" />
                                <span class="truncate font-medium">{{ flight.destination.city }}</span>
                            </div>
                            <dl class="mt-2 grid grid-cols-2 gap-3 text-[0.8125rem]">
                                <div>
                                    <dt class="text-[var(--vyamap-text-subtle)]">Departure</dt>
                                    <dd class="vyamap-number text-[var(--vyamap-text-muted)]">{{ flightDepartureLabel(flight, false) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[var(--vyamap-text-subtle)]">Arrival</dt>
                                    <dd class="vyamap-number text-[var(--vyamap-text-muted)]">{{ formatFlightDateTime(flight.arrival, flight.destination.timezone) }}</dd>
                                </div>
                            </dl>
                        </li>
                    </ul>

                    <EmptyState
                        v-else
                        compact
                        icon="flight"
                        title="No flights yet"
                        text="Flights draw their routes on the map and add up your distance."
                    >
                        <template #actions>
                            <Link href="/flight-history" class="vyamap-button-secondary vyamap-button-sm">
                                <Icon name="plus" :size="16" />
                                Add a flight
                            </Link>
                        </template>
                    </EmptyState>
                </div>

                <div class="vyamap-map-card min-h-[320px]">
                    <div class="vyamap-map-overlay left-4 top-4">
                        <h2 class="text-sm font-semibold">Flight map</h2>
                        <p class="vyamap-number text-xs text-[var(--vyamap-text-muted)]">
                            {{ stats.flights }} {{ stats.flights === 1 ? 'flight' : 'flights' }}
                        </p>
                    </div>
                    <div class="h-[320px] w-full sm:h-[440px]">
                        <TravelMap :cities="[]" :flights="map.flights" />
                    </div>
                </div>
            </section>

            <!-- FOTOS -->
            <section v-if="!isFirstRun && (recentPhotos.length || uploadsEnabled)" class="vyamap-panel">
                <div class="vyamap-panel-header">
                    <h2 class="vyamap-h2">Recent photos</h2>
                    <Link href="/photos" class="vyamap-link text-sm">View all photos</Link>
                </div>

                <ul v-if="recentPhotos.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                    <li v-for="photo in recentPhotos.slice(0, 7)" :key="photo.id">
                        <Link
                            href="/photos"
                            class="block aspect-square overflow-hidden rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)]"
                        >
                            <img :src="photo.thumbnail_url" :alt="photo.original_filename" class="h-full w-full object-cover" loading="lazy" />
                        </Link>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    compact
                    icon="photo"
                    title="No photos yet"
                    text="Photos added to your trips appear here. They are private and only you can see them."
                />
            </section>
        </div>
    </AppLayout>
</template>

<style>
.vyamap-trip-thumb {
    display: flex;
    height: 100%;
    width: 100%;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(circle at 30% 25%, rgba(34, 211, 238, 0.28), transparent 60%),
        radial-gradient(circle at 80% 85%, rgba(124, 92, 255, 0.3), transparent 60%),
        var(--vyamap-surface-strong);
    color: var(--vyamap-text);
}
</style>
