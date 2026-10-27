<script setup lang="ts">

import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { tripPhaseBadge, tripPhaseLabel } from '../../utils/tripPhase';
import { tripPath } from '../../utils/routes';

interface Country {
    id: number;
    name: string;
    iso_code: string;
}

interface City {
    id: number;
    name: string;
    // Solo visitas de viajes completados.
    visits_count: number;
    planned: boolean;
}

interface Trip {
    id: number;
    phase: 'upcoming' | 'ongoing' | 'pending' | 'completed';
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    cover: string | null;
    cities: string[];
}

const props = defineProps<{
    country: Country;
    cities: City[];
    trips: Trip[];
}>();

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(letter.charCodeAt(0) + 127397),
        )
        .join('');
};

const formatDate = (date: string | null) => {
    if (!date) {
        return '';
    }

    return new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

</script>

<template>
    <AppLayout :title="country.name">
        <div class="vyamap-container vyamap-stack">
            <section>
                <Link href="/countries" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-3">
                    <Icon name="arrow-left" :size="16" />
                    Countries
                </Link>

                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-[var(--vyamap-radius-lg)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] text-4xl" aria-hidden="true">
                            {{ flagEmoji(country.iso_code) }}
                        </span>
                        <div>
                            <h1 class="vyamap-page-title">{{ country.name }}</h1>
                            <p class="vyamap-page-description !mt-1">A record of the places and trips you've experienced here.</p>
                        </div>
                    </div>

                    <dl class="flex gap-8">
                        <div class="vyamap-stat">
                            <dt class="vyamap-stat-label order-2">{{ cities.length === 1 ? 'City' : 'Cities' }}</dt>
                            <dd class="vyamap-stat-value order-1">{{ cities.length }}</dd>
                        </div>
                        <div class="vyamap-stat">
                            <dt class="vyamap-stat-label order-2">{{ trips.length === 1 ? 'Trip' : 'Trips' }}</dt>
                            <dd class="vyamap-stat-value order-1">{{ trips.length }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- CIUDADES -->
            <section class="vyamap-panel">
                <div class="vyamap-panel-header">
                    <h2 class="vyamap-h2">Cities visited</h2>
                    <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ cities.length }} {{ cities.length === 1 ? 'city' : 'cities' }}</span>
                </div>

                <ul v-if="cities.length" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="city in cities" :key="city.id" class="vyamap-flight flex items-center gap-3">
                        <Icon name="pin" :size="18" :class="city.visits_count ? 'text-[var(--vyamap-cyan-soft)]' : 'text-[var(--vyamap-violet)]'" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ city.name }}</span>
                            <span class="vyamap-number block text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                <template v-if="city.visits_count">{{ city.visits_count }} {{ city.visits_count === 1 ? 'visit' : 'visits' }}</template>
                                <template v-if="city.visits_count && city.planned"> · </template>
                                <span v-if="city.planned" class="text-[var(--vyamap-violet)]">{{ city.visits_count ? 'planned' : 'Planned' }}</span>
                            </span>
                        </span>
                    </li>
                </ul>
                <EmptyState v-else compact icon="pin" title="No cities recorded yet" text="Cities appear when a trip has a visit in this country." />
            </section>

            <!-- VIAJES -->
            <section class="vyamap-panel">
                <div class="vyamap-panel-header">
                    <h2 class="vyamap-h2">Trips in {{ country.name }}</h2>
                    <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ trips.length }} {{ trips.length === 1 ? 'trip' : 'trips' }}</span>
                </div>

                <ul v-if="trips.length" class="-mx-2 flex flex-col">
                    <li v-for="trip in trips" :key="trip.id">
                        <Link :href="tripPath(trip)" class="vyamap-row vyamap-row-link group flex items-center gap-4 px-2 py-3">
                            <span class="relative h-14 w-14 shrink-0 overflow-hidden rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)]">
                                <img v-if="trip.cover" :src="trip.cover" alt="" class="h-full w-full object-cover" loading="lazy" />
                                <span v-else class="flex h-full w-full items-center justify-center text-[var(--vyamap-text-muted)]">
                                    <Icon name="map" :size="20" />
                                </span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-[0.9375rem] font-semibold group-hover:text-[var(--vyamap-cyan-soft)]">{{ trip.name }}</span>
                                    <span v-if="trip.phase !== 'completed'" class="vyamap-badge" :class="tripPhaseBadge[trip.phase]">{{ tripPhaseLabel[trip.phase] }}</span>
                                </span>
                                <span class="block truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                    <span class="vyamap-number">{{ formatDate(trip.start_date) }}<template v-if="trip.end_date"> – {{ formatDate(trip.end_date) }}</template></span>
                                    <template v-if="trip.cities.length"> · {{ trip.cities.join(', ') }}</template>
                                </span>
                            </span>
                            <Icon name="chevron-right" :size="18" class="shrink-0 text-[var(--vyamap-text-subtle)] group-hover:text-[var(--vyamap-text)]" />
                        </Link>
                    </li>
                </ul>
                <EmptyState v-else compact icon="trips" title="No trips recorded in this country yet" />
            </section>
        </div>
    </AppLayout>
</template>
