<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { tripPhaseBadge, tripPhaseLabel } from '../../utils/tripPhase';
import { calendarYear, tripDays as calendarTripDays } from '../../utils/tripDates';
import type { CountryStatus } from '../../utils/countryStatus';
import { emptyYear, type PlaceCounts, type YearPlaceStats } from '../../types/statistics';
import { tripPath } from '../../utils/routes';
import StockImage from '../../components/ui/StockImage.vue';
import { stockImage, stockImageFor } from '../../data/stockImages';

interface Country {
    name: string;
    iso_code: string;
    country_status: CountryStatus;
}

interface Trip {
    id: number;
    status: 'planned' | 'completed' | 'cancelled';
    phase: 'upcoming' | 'ongoing' | 'pending' | 'completed' | 'cancelled';
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    cover: string | null;
    city_ids: number[];
    cities: string[];
    countries: Country[];
}

const props = defineProps<{
    trips: Trip[];
    // Cifras calculadas en el servidor (solo viajes completados).
    stats: { total: PlaceCounts; by_year: Record<string, YearPlaceStats> };
}>();

const selectedYear = ref<'all' | number>('all');

// Un viaje se lista en el año en que empieza (las cifras de viajes del servidor
// usan la misma regla); sus días se reparten por año natural en el servidor.
const years = computed(() =>
    [...new Set(
        props.trips
            .map((trip) => calendarYear(trip.start_date))
            .filter((year): year is number => year !== null),
    )].sort((a, b) => b - a),
);

const filteredTrips = computed(() => {
    if (selectedYear.value === 'all') {
        return props.trips;
    }

    return props.trips.filter((trip) => calendarYear(trip.start_date) === selectedYear.value);
});

// Cifras resumen del servidor: solo viajes completados (historial real).
// Viajes por año de inicio; días como unión de intervalos repartida por año
// natural (un viaje de fin de año aporta días a los dos años).
const periodStats = computed<PlaceCounts>(() =>
    selectedYear.value === 'all'
        ? props.stats.total
        : props.stats.by_year[selectedYear.value] ?? emptyYear(),
);

const plannedCount = computed(() =>
    filteredTrips.value.filter((trip) => trip.status === 'planned').length,
);

const formatDate = (date: string | null) => {
    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
    })
        .format(new Date(`${date}T00:00:00`));
};

const formatDateRange = (trip: Trip) => {
    if (!trip.start_date && !trip.end_date) {
        return '';
    }

    if (!trip.end_date) {
        return formatDate(trip.start_date);
    }

    return `${formatDate(trip.start_date)} – ${formatDate(trip.end_date)}`;
};

const tripYear = (trip: Trip) => calendarYear(trip.start_date) ?? '';

const tripDays = (trip: Trip) => calendarTripDays(trip.start_date, trip.end_date);

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(letter.charCodeAt(0) + 127397),
        )
        .join('');
};


</script>

<template>
    <AppLayout title="Trips">
        <div class="vyamap-container">
            <header class="vyamap-page-header">
                <div>
                    <h1 class="vyamap-page-title">Trips</h1>
                    <p class="vyamap-page-description">Every journey in one place.</p>
                </div>
                <Link href="/trips/create" class="vyamap-button hidden lg:inline-flex">
                    <Icon name="plus" :size="16" />
                    New trip
                </Link>
            </header>

            <template v-if="trips.length">
                <!-- CIFRAS DEL PERIODO (servidor: solo viajes completados) -->
                <section class="vyamap-card mb-4" aria-label="Period statistics">
                    <dl class="grid grid-cols-2 sm:grid-cols-4">
                        <div class="vyamap-stat p-4 sm:p-5">
                            <dt class="vyamap-stat-label order-2">{{ periodStats.trips === 1 ? 'Trip' : 'Trips' }}</dt>
                            <dd class="vyamap-stat-value order-1">{{ periodStats.trips }}</dd>
                            <dd v-if="plannedCount" class="vyamap-stat-hint order-3 !text-[var(--vyamap-violet)]">+ {{ plannedCount }} planned</dd>
                        </div>
                        <div class="vyamap-stat border-l border-[var(--vyamap-border)] p-4 sm:p-5">
                            <dt class="vyamap-stat-label order-2">Days</dt>
                            <dd class="vyamap-stat-value order-1">{{ periodStats.days }}</dd>
                        </div>
                        <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-4 sm:border-l sm:border-t-0 sm:p-5">
                            <dt class="vyamap-stat-label order-2">{{ periodStats.countries === 1 ? 'Country' : 'Countries' }}</dt>
                            <dd class="vyamap-stat-value order-1">{{ periodStats.countries }}</dd>
                            <dd v-if="periodStats.territories" class="vyamap-stat-hint order-3">
                                + {{ periodStats.territories }} {{ periodStats.territories === 1 ? 'territory' : 'territories' }}
                            </dd>
                        </div>
                        <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-4 sm:border-t-0 sm:p-5">
                            <dt class="vyamap-stat-label order-2">{{ periodStats.cities === 1 ? 'City' : 'Cities' }}</dt>
                            <dd class="vyamap-stat-value order-1">{{ periodStats.cities }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- FILTRO POR AÑO -->
                <section class="mb-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="vyamap-segmented" role="group" aria-label="Filter by year">
                            <button
                                type="button"
                                class="vyamap-segment"
                                :aria-pressed="selectedYear === 'all'"
                                @click="selectedYear = 'all'"
                            >
                                All
                            </button>
                            <button
                                v-for="year in years"
                                :key="year"
                                type="button"
                                class="vyamap-segment vyamap-number"
                                :aria-pressed="selectedYear === year"
                                @click="selectedYear = year"
                            >
                                {{ year }}
                            </button>
                        </div>
                        <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">
                            {{ filteredTrips.length }} {{ filteredTrips.length === 1 ? 'trip' : 'trips' }}
                        </span>
                    </div>

                    <!-- Criterios distintos: viajes por año de inicio; días, países y ciudades por año natural. -->
                    <p v-if="selectedYear !== 'all'" class="vyamap-help mt-2">
                        Trips that start in {{ selectedYear }}. Days, countries and cities count everything within {{ selectedYear }}, including trips that started the year before.
                    </p>
                </section>

                <!-- VIAJES -->
                <ul v-if="filteredTrips.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <li v-for="trip in filteredTrips" :key="trip.id">
                        <Link :href="tripPath(trip)" class="vyamap-trip-card group flex h-full flex-col overflow-hidden">
                            <div class="relative aspect-[16/9] overflow-hidden border-b border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)]">
                                <img
                                    v-if="trip.cover"
                                    :src="trip.cover"
                                    alt=""
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                                <StockImage
                                    v-else
                                    :image="stockImageFor(trip.id)"
                                    sizes="(min-width: 1280px) 33vw, (min-width: 640px) 50vw, 100vw"
                                />

                                <span
                                    v-if="trip.phase !== 'completed'"
                                    class="vyamap-badge absolute left-3 top-3 !bg-[rgba(7,11,20,0.75)]"
                                    :class="[tripPhaseBadge[trip.phase], trip.phase === 'cancelled' ? 'line-through' : '']"
                                >
                                    {{ tripPhaseLabel[trip.phase] }}
                                </span>
                            </div>

                            <div class="flex flex-1 flex-col gap-1 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 class="text-[1.0625rem] font-semibold tracking-[-0.015em] group-hover:text-[var(--vyamap-cyan-soft)]">
                                        {{ trip.name }}
                                    </h2>
                                    <span class="vyamap-number shrink-0 pt-0.5 text-sm text-[var(--vyamap-text-subtle)]">{{ tripYear(trip) }}</span>
                                </div>
                                <p class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ formatDateRange(trip) }}</p>

                                <div class="mt-auto flex items-center justify-between gap-3 border-t border-[var(--vyamap-border)] pt-3 text-[0.8125rem]">
                                    <span v-if="trip.countries.length" class="flex min-w-0 items-center gap-1.5 text-[var(--vyamap-text-muted)]">
                                        <span aria-hidden="true">{{ trip.countries.map((country) => flagEmoji(country.iso_code)).join(' ') }}</span>
                                        <span class="truncate">{{ trip.countries.map((country) => country.name).join(', ') }}</span>
                                    </span>
                                    <span v-else class="text-[var(--vyamap-text-subtle)]">No destinations recorded</span>

                                    <span class="vyamap-number shrink-0 text-[var(--vyamap-text-subtle)]">
                                        <template v-if="tripDays(trip)">{{ tripDays(trip) }} days</template>
                                        <template v-if="tripDays(trip) && trip.cities.length"> · </template>
                                        <template v-if="trip.cities.length">{{ trip.cities.length }} {{ trip.cities.length === 1 ? 'city' : 'cities' }}</template>
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </li>
                </ul>

                <EmptyState
                    v-else
                    icon="calendar"
                    title="No trips found"
                    text="There are no trips for this period."
                />
            </template>

            <EmptyState
                v-else
                icon="trips"
                :image="stockImage('mountain-lake')"
                title="No trips yet"
                text="Create a trip with its dates, then add the cities you visited and the flights you took. Each trip keeps its own map and statistics."
            >
                <template #actions>
                    <Link href="/trips/create" class="vyamap-button">
                        <Icon name="plus" :size="16" />
                        Create your first trip
                    </Link>
                </template>
            </EmptyState>
        </div>
    </AppLayout>
</template>
