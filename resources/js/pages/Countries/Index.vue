<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import EmptyState from '../../components/ui/EmptyState.vue';
import { countCountries, isTerritoryStatus, type CountryStatus } from '../../utils/countryStatus';
import { countryPath } from '../../utils/routes';

interface Country {
    id: number;
    slug: string;
    name: string;
    iso_code: string;
    country_status: CountryStatus;
    cities_count: number;
    trips_count: number;
}

const props = defineProps<{
    countries: Country[];
    // Países solo planificados: se listan aparte y no cuentan.
    plannedCountries: { id: number; slug: string; name: string; iso_code: string; country_status: CountryStatus }[];
}>();

// Estados soberanos y territorios por separado; la lista muestra ambos.
const countryTotals = computed(() => countCountries(props.countries));

const search = ref('');

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(letter.charCodeAt(0) + 127397),
        )
        .join('');
};

const filteredCountries = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (!query) {
        return props.countries;
    }

    return props.countries.filter((country) =>
        country.name.toLowerCase().includes(query),
    );
});

</script>

<template>
    <AppLayout title="Countries">
        <div class="vyamap-container vyamap-stack">
            <header class="vyamap-page-header !mb-0">
                <div>
                    <h1 class="vyamap-page-title">Countries</h1>
                    <p class="vyamap-page-description">
                        Every country you've visited, organised in one place.
                        <span class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ countryTotals.countries }}</span>
                        {{ countryTotals.countries === 1 ? 'country' : 'countries' }}<template v-if="countryTotals.territories">
                            · <span class="vyamap-number font-semibold text-[var(--vyamap-text)]">{{ countryTotals.territories }}</span>
                            {{ countryTotals.territories === 1 ? 'territory' : 'territories' }}</template>.
                    </p>
                </div>

                <div v-if="countries.length" class="relative w-full sm:w-72">
                    <label for="country-search" class="sr-only">Search countries</label>
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--vyamap-text-subtle)]">
                        <Icon name="search" :size="18" />
                    </span>
                    <input id="country-search" v-model="search" type="search" placeholder="Search countries..." class="vyamap-input !pl-10" />
                </div>
            </header>

            <ul v-if="filteredCountries.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="country in filteredCountries" :key="country.id">
                    <Link :href="countryPath(country)" class="vyamap-trip-card group flex items-center gap-4 p-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface-strong)] text-2xl" aria-hidden="true">
                            {{ flagEmoji(country.iso_code) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate text-[0.9375rem] font-semibold group-hover:text-[var(--vyamap-cyan-soft)]">{{ country.name }}</span>
                                <span v-if="isTerritoryStatus(country.country_status)" class="vyamap-badge vyamap-badge-plain">Territory</span>
                            </span>
                            <span class="vyamap-number block text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                                {{ country.cities_count }} {{ country.cities_count === 1 ? 'city' : 'cities' }}
                                · {{ country.trips_count }} {{ country.trips_count === 1 ? 'trip' : 'trips' }}
                            </span>
                        </span>
                        <Icon name="chevron-right" :size="18" class="shrink-0 text-[var(--vyamap-text-subtle)] group-hover:text-[var(--vyamap-text)]" />
                    </Link>
                </li>
            </ul>

            <EmptyState v-else-if="search" icon="search" title="No countries found" text="Try searching with another country name.">
                <template #actions>
                    <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="search = ''">Clear search</button>
                </template>
            </EmptyState>

            <EmptyState
                v-else
                icon="globe"
                title="No countries yet"
                text="Your visited countries will appear here once you start adding trips. A country counts when a completed trip has a city in it."
            >
                <template #actions>
                    <Link href="/trips/create" class="vyamap-button">
                        <Icon name="plus" :size="16" />
                        New trip
                    </Link>
                </template>
            </EmptyState>

            <!-- PLANIFICADOS: destinos de viajes planificados, todavía no visitados -->
            <section v-if="props.plannedCountries.length" class="vyamap-panel">
                <div class="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="vyamap-h2">Planned</h2>
                    <p class="text-sm text-[var(--vyamap-text-muted)]">Not counted until the trip is completed.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="country in props.plannedCountries"
                        :key="country.id"
                        :href="countryPath(country)"
                        class="vyamap-badge vyamap-badge-violet vyamap-badge-plain !min-h-8 !px-3 !text-[0.8125rem] hover:bg-[rgba(167,139,250,0.22)]"
                    >
                        <span aria-hidden="true">{{ flagEmoji(country.iso_code) }}</span>
                        {{ country.name }}
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
