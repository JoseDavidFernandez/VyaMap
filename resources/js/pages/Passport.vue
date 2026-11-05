<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import Icon from '../components/ui/Icon.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import CountryMap from '../components/dashboard/CountryMap.vue';
import { calendarYear, touchesYear } from '../utils/tripDates';
import type { CountryStatus } from '../utils/countryStatus';
import { emptyYear, type FlightStats, type PlaceStats } from '../types/statistics';

interface Visit {
    id: number;
    city_id: number;
    city: string;
    country: string;
    iso_code: string;
    is_sovereign: boolean;
    country_status: CountryStatus;
    visited_from: string | null;
    visited_until: string | null;
}

interface Trip {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

interface AirportPoint {
    code: string | null;
    airport: string;
    city: string;
    latitude: number;
    longitude: number;
    timezone: string | null;
}

interface Flight {
    id: number;
    departure_date: string;
    flight_number: string | null;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    // null si falta alguna coordenada (nunca se inventa una distancia).
    distance_km: number | null;
    duration_minutes: number | null;
    origin: AirportPoint;
    destination: AirportPoint;
}

interface AuthUser {
    name: string;
    avatar: string | null;
}

const page = usePage();
const authUser = computed(() =>
    (page.props.auth as { user?: AuthUser } | undefined)?.user,
);
const userName = computed(() => authUser.value?.name ?? 'VyaMap');
const userAvatar = computed(() => {
    const avatar = authUser.value?.avatar ?? null;

    if (!avatar) {
        return null;
    }

    return avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')
        ? avatar
        : `/storage/${avatar}`;
});

interface PassportStamp {
    id: number;
    iso_code: string;
    country: string;
    date: string | null;
    rotation: string;
    type: 'entry' | 'visit';
}

const props = defineProps<{
    totalCountries: number;
    years: number[];
    visits: Visit[];
    trips: Trip[];
    flights: Flight[];
    plannedCountries: { iso_code: string; name: string; ongoing: boolean }[];
    // Cifras calculadas en el servidor (total y por año natural).
    stats: PlaceStats;
    flightStats: { all: FlightStats; by_year: Record<string, FlightStats> };
}>();

const selectedPeriod = ref<number | 'all'>(
    'all',
);

/*
|--------------------------------------------------------------------------
| Period
|--------------------------------------------------------------------------
*/

const periodVisits = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.visits;
    }

    const year = selectedPeriod.value;

    return props.visits.filter((visit) => touchesYear(visit.visited_from, visit.visited_until, year));
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
            is_sovereign: boolean;
        }
    >();

    periodVisits.value.forEach((visit) => {
        if (!countries.has(visit.iso_code)) {
            countries.set(visit.iso_code, {
                id: visit.city_id,
                name: visit.country,
                iso_code: visit.iso_code,
                is_sovereign: visit.is_sovereign,
            });
        }
    });

    return Array.from(
        countries.values(),
    ).sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});

/*
|--------------------------------------------------------------------------
| Cifras del periodo (servidor)
|--------------------------------------------------------------------------
*/

const periodStats = computed(() =>
    selectedPeriod.value === 'all'
        ? props.stats.total
        : props.stats.by_year[selectedPeriod.value] ?? emptyYear(),
);

// null: ese año no hay vuelos realizados.
const periodFlightStats = computed<FlightStats | null>(() =>
    selectedPeriod.value === 'all'
        ? props.flightStats.all
        : props.flightStats.by_year[selectedPeriod.value] ?? null,
);

// Los territorios se muestran en el mapa, pero el porcentaje y el
// contador "X / total" solo cuentan Estados soberanos.
const visitedSovereignCount = computed(() => periodStats.value.countries);

// Territorios visitados en el periodo: se muestran aparte, no suman a los 193.
const visitedTerritoriesCount = computed(() => periodStats.value.territories);

const visitedCities = computed(() => periodStats.value.cities);

const visitedContinents = computed(() => props.stats.continents.items.filter((continent) => continent.visited));

// Primeras visitas: países soberanos que se visitaron por primera vez cada año.
const discoveriesByYear = computed(() =>
    Object.entries(props.stats.by_year)
        .map(([year, stats]) => ({ year: Number(year), countries: stats.new_countries, territories: stats.new_territories }))
        .filter((item) => item.countries.length || item.territories)
        .filter((item) => selectedPeriod.value === 'all' || item.year === selectedPeriod.value)
        .sort((a, b) => b.year - a.year),
);

const countriesPercentage = computed(() => {
    if (!props.totalCountries) {
        return 0;
    }

    return (
        (visitedSovereignCount.value /
            props.totalCountries) *
        100
    );
});

const activePassportPage = ref(1);
const activePassportStamp = ref<PassportStamp | null>(null);

const passportStamps = computed<PassportStamp[]>(() => {
    const rotations = ['-3deg', '2deg', '-2deg', '3deg', '-4deg', '2deg', '-2deg', '4deg'];
    const countries = new Map<string, Visit>();

    [...periodVisits.value]
        .sort((a, b) => {
            const dateA = a.visited_from ? new Date(a.visited_from).getTime() : 0;
            const dateB = b.visited_from ? new Date(b.visited_from).getTime() : 0;
            return dateA - dateB;
        })
        .forEach((visit) => {
            if (!countries.has(visit.iso_code)) {
                countries.set(visit.iso_code, visit);
            }
        });

    return Array.from(countries.values()).map((visit, index) => ({
        id: visit.id,
        iso_code: visit.iso_code,
        country: visit.country,
        date: visit.visited_from,
        rotation: rotations[index % rotations.length],
        type: 'entry' as const,
    }));
});

const passportPageSize = 6;

const passportTotalPages = computed(() =>
    Math.max(1, Math.ceil(passportStamps.value.length / passportPageSize)),
);

const currentPassportStamps = computed(() => {
    const start = (activePassportPage.value - 1) * passportPageSize;
    return passportStamps.value.slice(start, start + passportPageSize);
});

const passportCountriesPercentage = computed(() => {
    if (!props.totalCountries) {
        return 0;
    }

    return Number(
        Math.min(100, (visitedSovereignCount.value / props.totalCountries) * 100).toFixed(1),
    );
});

// null si ningún vuelo del periodo tiene coordenadas en ambos aeropuertos.
const passportTotalDistance = computed(() => periodFlightStats.value?.distance_km ?? null);

const stampLabel = (stamp: PassportStamp) => {
    return stamp.type === 'entry' ? 'ENTRY' : 'VISIT';
};

const formatPassportDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(date)).toUpperCase();
};

const selectPassportStamp = (stamp: PassportStamp) => {
    activePassportStamp.value =
        activePassportStamp.value?.id === stamp.id ? null : stamp;
};

const goToPassportPage = (page: number) => {
    activePassportPage.value = Math.min(
        passportTotalPages.value,
        Math.max(1, page),
    );
    activePassportStamp.value = null;
};

/*
|--------------------------------------------------------------------------
| Recent visits
|--------------------------------------------------------------------------
*/

const recentVisits = computed(() => {
    return [...periodVisits.value]
        .sort((a, b) => {
            const dateA = a.visited_from
                ? new Date(
                      a.visited_from,
                  ).getTime()
                : 0;

            const dateB = b.visited_from
                ? new Date(
                      b.visited_from,
                  ).getTime()
                : 0;

            return dateB - dateA;
        })
        .slice(0, 5);
});

/*
|--------------------------------------------------------------------------
| Yearly trips
|--------------------------------------------------------------------------
*/

// Viajes completados por año natural (servidor): un viaje está en cada año que toca.
const tripsByYear = computed(() =>
    Object.entries(props.stats.by_year)
        .map(([year, stats]) => ({ year: Number(year), count: stats.trips }))
        .filter((item) => item.count > 0)
        .filter((item) => selectedPeriod.value === 'all' || item.year === selectedPeriod.value)
        .sort((a, b) => b.year - a.year),
);

const maxTripsByYear = computed(() => {
    return Math.max(
        1,
        ...tripsByYear.value.map(
            (item) => item.count,
        ),
    );
});


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const flagEmoji = (
    isoCode: string,
) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(
                letter.charCodeAt(0) +
                    127397,
            ),
        )
        .join('');
};

const formatNumber = (
    value: number,
) => {
    return new Intl.NumberFormat(
        'en-GB',
    ).format(Math.round(value));
};

const formatDate = (
    date: string | null,
) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            // Fechas de calendario (medianoche UTC): en UTC el día no cambia.
            timeZone: 'UTC',
        },
    ).format(new Date(date));

};


// Zona tipo MRZ con datos reales del titular (sin números ni fechas inventados).
const machineReadable = computed(() => {
    const name = userName.value.toUpperCase().replace(/[^A-Z0-9]+/g, '<').replace(/^<+|<+$/g, '') || 'VYAMAP';
    const line1 = `P<VYA<<${name}`.padEnd(36, '<').slice(0, 36);
    const line2 = `${String(visitedSovereignCount.value).padStart(3, '0')}C<<${String(visitedCities.value).padStart(4, '0')}T<<${String(periodStats.value.trips).padStart(3, '0')}V`.padEnd(36, '<').slice(0, 36);

    return [line1, line2];
});
</script>

<template>
    <AppLayout title="Passport">
        <div class="vyamap-container vyamap-stack">
            <header class="vyamap-page-header !mb-0">
                <div>
                    <h1 class="vyamap-page-title">Passport</h1>
                    <p class="vyamap-page-description">A visual record of the places you have visited and the journeys you have taken.</p>
                </div>

                <div class="vyamap-segmented" role="group" aria-label="Period">
                    <button type="button" class="vyamap-segment" :aria-pressed="selectedPeriod === 'all'" @click="selectedPeriod = 'all'">All-Time</button>
                    <button
                        v-for="year in props.years"
                        :key="year"
                        type="button"
                        class="vyamap-segment vyamap-number"
                        :aria-pressed="selectedPeriod === year"
                        @click="selectedPeriod = year"
                    >
                        {{ year }}
                    </button>
                </div>
            </header>

            <!-- PASAPORTE -->
            <section aria-label="VyaMap passport">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="vyamap-h2">VyaMap Passport</h2>
                        <p class="mt-0.5 text-sm text-[var(--vyamap-text-muted)]">
                            One stamp for the first entry into each country<template v-if="selectedPeriod !== 'all'"> in {{ selectedPeriod }}</template>.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="vyamap-icon-button"
                            aria-label="Previous page"
                            :disabled="activePassportPage === 1"
                            @click="goToPassportPage(activePassportPage - 1)"
                        >
                            <Icon name="chevron-left" :size="18" />
                        </button>
                        <span class="vyamap-number min-w-[5.5rem] text-center text-sm text-[var(--vyamap-text-muted)]">
                            Page {{ activePassportPage }} / {{ passportTotalPages }}
                        </span>
                        <button
                            type="button"
                            class="vyamap-icon-button"
                            aria-label="Next page"
                            :disabled="activePassportPage === passportTotalPages"
                            @click="goToPassportPage(activePassportPage + 1)"
                        >
                            <Icon name="chevron-right" :size="18" />
                        </button>
                    </div>
                </div>

                <div class="passport-book">
                    <!-- PÁGINA DE DATOS -->
                    <div class="passport-sheet passport-sheet-left">
                        <span class="passport-watermark" aria-hidden="true">V</span>

                        <div class="passport-sheet-inner">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="passport-micro">VyaMap · Travel passport</p>
                                    <h3 class="mt-1.5 text-[1.375rem] font-semibold tracking-[-0.02em]">{{ userName }}</h3>
                                </div>
                                <span class="passport-emblem" aria-hidden="true">V</span>
                            </div>

                            <div class="mt-6 flex gap-5">
                                <div class="passport-photo">
                                    <img v-if="userAvatar" :src="userAvatar" alt="" class="h-full w-full object-cover" />
                                    <span v-else>{{ userName.charAt(0).toUpperCase() }}</span>
                                    <span class="passport-photo-seal" aria-hidden="true" />
                                </div>

                                <dl class="passport-fields">
                                    <div>
                                        <dt>Document</dt>
                                        <dd>Travel passport</dd>
                                    </div>
                                    <div>
                                        <dt>Holder</dt>
                                        <dd>{{ userName }}</dd>
                                    </div>
                                    <div>
                                        <dt>Period</dt>
                                        <dd>{{ selectedPeriod === 'all' ? 'All-time' : selectedPeriod }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="passport-figures">
                                <div
                                    class="passport-ring"
                                    role="img"
                                    :aria-label="`${passportCountriesPercentage}% of the world's countries`"
                                    :style="{ '--value': `${Math.min(100, passportCountriesPercentage)}%` }"
                                >
                                    <span class="vyamap-number">{{ passportCountriesPercentage }}%</span>
                                    <small>world</small>
                                </div>
                                <dl class="grid flex-1 grid-cols-3 gap-2">
                                    <div class="passport-figure">
                                        <dd class="vyamap-number">{{ visitedSovereignCount }}<small>/{{ props.totalCountries }}</small></dd>
                                        <dt>Countries</dt>
                                    </div>
                                    <div class="passport-figure">
                                        <dd class="vyamap-number">{{ visitedCities }}</dd>
                                        <dt>Cities</dt>
                                    </div>
                                    <div class="passport-figure">
                                        <dd class="vyamap-number">{{ periodStats.trips }}</dd>
                                        <dt>Trips</dt>
                                    </div>
                                </dl>
                            </div>

                            <div class="passport-mrz" aria-hidden="true">
                                <div v-for="line in machineReadable" :key="line">{{ line }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- PÁGINA DE SELLOS -->
                    <div class="passport-sheet passport-sheet-right">
                        <div class="passport-sheet-inner">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="passport-micro">Visas &amp; stamps</p>
                                    <h3 class="mt-1.5 text-lg font-semibold tracking-[-0.01em]">Places visited</h3>
                                </div>
                                <span class="passport-micro vyamap-number">{{ String(activePassportPage).padStart(2, '0') }}</span>
                            </div>

                            <ul v-if="currentPassportStamps.length" class="passport-stamps">
                                <li v-for="(stamp, index) in currentPassportStamps" :key="stamp.id">
                                    <button
                                        type="button"
                                        class="passport-stamp"
                                        :class="[index % 3 === 1 ? 'is-violet' : 'is-cyan', { 'is-active': activePassportStamp?.id === stamp.id }]"
                                        :style="{ '--stamp-rotation': stamp.rotation }"
                                        :aria-pressed="activePassportStamp?.id === stamp.id"
                                        :aria-label="`${stamp.country}, first entry ${formatDate(stamp.date)}`"
                                        @click="selectPassportStamp(stamp)"
                                    >
                                        <span class="passport-stamp-ring">
                                            <span class="text-lg leading-none" aria-hidden="true">{{ flagEmoji(stamp.iso_code) }}</span>
                                            <span class="passport-stamp-country">{{ stamp.country }}</span>
                                            <span class="passport-stamp-type">{{ stampLabel(stamp) }}</span>
                                            <span class="passport-stamp-date vyamap-number">{{ formatPassportDate(stamp.date) }}</span>
                                        </span>
                                    </button>
                                </li>
                            </ul>

                            <div v-else class="passport-empty">
                                <Icon name="passport" :size="28" />
                                <p class="font-semibold text-[var(--vyamap-text)]">No stamps yet</p>
                                <p>Each country you visit on a completed trip adds a stamp here.</p>
                                <Link href="/trips" class="vyamap-button-secondary vyamap-button-sm mt-2">Go to my trips</Link>
                            </div>

                            <div class="passport-sheet-footer">
                                <div>
                                    <p class="passport-micro">Valid for</p>
                                    <p class="text-[0.75rem] font-semibold tracking-[0.06em]">PERSONAL TRAVEL HISTORY</p>
                                </div>
                                <span class="passport-micro vyamap-number">VYM / {{ String(activePassportPage).padStart(2, '0') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="passportTotalPages > 1" class="mt-3 flex justify-center gap-2">
                    <button
                        v-for="pageNumber in passportTotalPages"
                        :key="pageNumber"
                        type="button"
                        class="passport-dot"
                        :class="{ 'is-active': activePassportPage === pageNumber }"
                        :aria-label="`Page ${pageNumber}`"
                        :aria-current="activePassportPage === pageNumber ? 'page' : undefined"
                        @click="goToPassportPage(pageNumber)"
                    />
                </div>

                <p
                    v-if="activePassportStamp"
                    role="status"
                    class="mx-auto mt-4 flex max-w-[60rem] items-center justify-between gap-3 rounded-[var(--vyamap-radius-md)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm"
                >
                    <span class="flex items-center gap-2 font-semibold">
                        <span aria-hidden="true">{{ flagEmoji(activePassportStamp.iso_code) }}</span>
                        {{ activePassportStamp.country }}
                    </span>
                    <span class="vyamap-number text-[var(--vyamap-text-muted)]">First entry · {{ formatDate(activePassportStamp.date) }}</span>
                </p>
            </section>

            <!-- DE UN VISTAZO -->
            <section class="vyamap-card overflow-hidden" aria-label="At a glance">
                <dl class="grid grid-cols-2 lg:grid-cols-3">
                    <div class="vyamap-stat gap-2 p-5">
                        <dt class="vyamap-stat-label order-2">Countries</dt>
                        <dd class="vyamap-stat-value order-1">{{ visitedSovereignCount }}<span class="vyamap-stat-unit">/ {{ props.totalCountries }}</span></dd>
                        <dd class="order-3"><div class="vyamap-progress"><span :style="{ width: `${Math.max(passportCountriesPercentage, visitedSovereignCount ? 1 : 0)}%` }" /></div></dd>
                        <dd v-if="visitedTerritoriesCount" class="vyamap-stat-hint order-4">
                            + {{ visitedTerritoriesCount }} {{ visitedTerritoriesCount === 1 ? 'territory' : 'territories' }}
                        </dd>
                    </div>
                    <div class="vyamap-stat border-l border-[var(--vyamap-border)] p-5">
                        <dt class="vyamap-stat-label order-2">Cities</dt>
                        <dd class="vyamap-stat-value order-1">{{ visitedCities }}</dd>
                    </div>
                    <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-5 lg:border-l lg:border-t-0">
                        <dt class="vyamap-stat-label order-2">Trips</dt>
                        <dd class="vyamap-stat-value order-1">{{ periodStats.trips }}</dd>
                        <dd v-if="selectedPeriod !== 'all'" class="vyamap-stat-hint order-3">Starting in {{ selectedPeriod }}</dd>
                    </div>
                    <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-5 lg:border-l-0">
                        <dt class="vyamap-stat-label order-2">{{ periodStats.days === 1 ? 'Day traveling' : 'Days traveling' }}</dt>
                        <dd class="vyamap-stat-value order-1">{{ formatNumber(periodStats.days) }}</dd>
                        <dd class="vyamap-stat-hint order-3">{{ selectedPeriod === 'all' ? 'Completed trips' : `Within ${selectedPeriod}` }}, overlaps counted once</dd>
                    </div>
                    <div class="vyamap-stat border-t border-[var(--vyamap-border)] p-5 lg:border-l">
                        <dt class="vyamap-stat-label order-2">Continents</dt>
                        <dd class="vyamap-stat-value order-1">{{ periodStats.continents }}<span class="vyamap-stat-unit">/ {{ props.stats.continents.items.length }}</span></dd>
                        <dd v-if="selectedPeriod === 'all' && visitedContinents.length" class="vyamap-stat-hint order-3">
                            {{ visitedContinents.map((continent) => `${continent.name} ${continent.countries}/${continent.total_countries}`).join(' · ') }}
                        </dd>
                    </div>
                    <div class="vyamap-stat border-l border-t border-[var(--vyamap-border)] p-5">
                        <dt class="vyamap-stat-label order-2">Flights</dt>
                        <dd class="vyamap-stat-value order-1">{{ periodFlightStats?.flights ?? 0 }}</dd>
                        <dd class="vyamap-stat-hint order-3 vyamap-number">
                            <template v-if="passportTotalDistance !== null">{{ formatNumber(passportTotalDistance) }} km</template>
                            <template v-else-if="periodFlightStats?.flights">Distance unknown</template>
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- MAPA -->
            <section class="vyamap-map-card h-[320px] sm:h-[460px]">
                <div class="vyamap-map-overlay left-4 top-4">
                    <h2 class="text-sm font-semibold">World explored</h2>
                    <p class="vyamap-number text-xs text-[var(--vyamap-text-muted)]">{{ visitedSovereignCount }} / {{ props.totalCountries }} countries</p>
                </div>
                <div class="vyamap-map-overlay bottom-4 left-4 hidden gap-4 text-xs sm:flex">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[var(--vyamap-cyan)]" />Visited</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[var(--vyamap-violet)]" />Planned</span>
                </div>
                <CountryMap :countries="visitedCountries" :planned="props.plannedCountries" theme="dark" />
            </section>

            <!-- DESCUBRIMIENTOS, RECIENTES Y AÑOS -->
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="vyamap-panel">
                    <h2 class="vyamap-h2 mb-3">Countries discovered by year</h2>
                    <ol v-if="discoveriesByYear.length" class="flex flex-col divide-y divide-[var(--vyamap-border)]">
                        <li v-for="item in discoveriesByYear" :key="item.year" class="flex gap-4 py-3 first:pt-0 last:pb-0">
                            <span class="vyamap-number w-12 shrink-0 text-sm font-semibold text-[var(--vyamap-cyan-soft)]">{{ item.year }}</span>
                            <span class="flex min-w-0 flex-1 flex-wrap gap-x-3 gap-y-1 text-sm">
                                <span v-for="country in item.countries" :key="country.iso_code">
                                    <span aria-hidden="true">{{ flagEmoji(country.iso_code) }}</span> {{ country.name }}
                                </span>
                                <span v-if="item.territories" class="text-[var(--vyamap-text-muted)]">
                                    + {{ item.territories }} {{ item.territories === 1 ? 'territory' : 'territories' }}
                                </span>
                            </span>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-[var(--vyamap-text-muted)]">Your first visit to each country will be listed by year.</p>
                </div>

                <div class="vyamap-panel">
                    <h2 class="vyamap-h2 mb-3">Recently visited</h2>
                    <ul v-if="recentVisits.length" class="flex flex-col divide-y divide-[var(--vyamap-border)]">
                        <li v-for="visit in recentVisits" :key="visit.id" class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                            <span class="text-xl leading-none" aria-hidden="true">{{ flagEmoji(visit.iso_code) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ visit.city }}</span>
                                <span class="block truncate text-[0.8125rem] text-[var(--vyamap-text-muted)]">{{ visit.country }}</span>
                            </span>
                            <span class="vyamap-number shrink-0 text-[0.8125rem] text-[var(--vyamap-text-subtle)]">{{ formatDate(visit.visited_from) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-[var(--vyamap-text-muted)]">No visits recorded yet.</p>
                </div>

                <div class="vyamap-panel">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 class="vyamap-h2">Trips by year</h2>
                        <span class="vyamap-number text-sm text-[var(--vyamap-text-muted)]">{{ periodStats.trips }} {{ periodStats.trips === 1 ? 'trip' : 'trips' }}</span>
                    </div>
                    <ul v-if="tripsByYear.length" class="flex flex-col gap-4">
                        <li v-for="item in tripsByYear" :key="item.year">
                            <div class="mb-1.5 flex items-baseline justify-between text-sm">
                                <span class="vyamap-number font-semibold">{{ item.year }}</span>
                                <span class="vyamap-number text-[var(--vyamap-text-muted)]">{{ item.count }} {{ item.count === 1 ? 'trip' : 'trips' }}</span>
                            </div>
                            <div class="vyamap-progress"><span :style="{ width: `${(item.count / maxTripsByYear) * 100}%` }" /></div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-[var(--vyamap-text-muted)]">No trips recorded yet.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
/*
 * Pasaporte: librillo abierto en papel de seguridad azul tinta. Guilloché y
 * grano muy tenues para que el texto mantenga el contraste; los sellos usan
 * las tintas del sistema (cian y violeta).
 */
.passport-book {
    position: relative;
    display: grid;
    grid-template-columns: 1fr;
    width: min(100%, 60rem);
    margin-inline: auto;
    overflow: hidden;
    border: 1px solid rgba(148, 170, 214, 0.22);
    border-radius: 1.25rem;
    background: #0B1224;
    box-shadow:
        0 1px 0 rgba(255, 255, 255, 0.06) inset,
        0 30px 70px -30px rgba(0, 0, 0, 0.95),
        0 0 0 6px rgba(13, 20, 36, 0.6);
}

@media (min-width: 768px) {
    .passport-book {
        grid-template-columns: 1fr 1fr;
        min-height: 34rem;
    }
}

.passport-sheet {
    position: relative;
    overflow: hidden;
    color: var(--vyamap-text);
    background:
        repeating-radial-gradient(circle at 18% 22%, rgba(34, 211, 238, 0.05) 0 1px, transparent 1px 9px),
        repeating-radial-gradient(circle at 85% 80%, rgba(167, 139, 250, 0.05) 0 1px, transparent 1px 11px),
        linear-gradient(160deg, #121C33 0%, #0D152A 55%, #0B1224 100%);
}

/* Grano de papel */
.passport-sheet::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(0deg, rgba(255, 255, 255, 0.012) 0 1px, transparent 1px 3px);
}

/* Lomo: sombra hacia el centro del libro */
.passport-sheet-left {
    border-bottom: 1px solid rgba(0, 0, 0, 0.5);
    box-shadow: inset 0 -18px 24px -18px rgba(0, 0, 0, 0.9);
}

@media (min-width: 768px) {
    .passport-sheet-left {
        border-bottom: 0;
        border-right: 1px solid rgba(0, 0, 0, 0.55);
        box-shadow: inset -26px 0 30px -24px rgba(0, 0, 0, 0.95);
    }

    .passport-sheet-right {
        box-shadow: inset 26px 0 30px -24px rgba(0, 0, 0, 0.95);
    }
}

.passport-sheet-inner {
    position: relative;
    z-index: 1;
    display: flex;
    height: 100%;
    flex-direction: column;
    padding: clamp(1.25rem, 3vw, 2rem);
}

.passport-micro {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--vyamap-text-subtle);
}

.passport-watermark {
    position: absolute;
    left: 50%;
    top: 46%;
    transform: translate(-50%, -50%);
    font-size: 17rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.06em;
    color: rgba(124, 155, 255, 0.045);
    pointer-events: none;
}

.passport-emblem {
    display: flex;
    height: 3rem;
    width: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    background:
        radial-gradient(circle at 30% 25%, rgba(255, 255, 255, 0.35), transparent 45%),
        conic-gradient(from 200deg, #22D3EE, #7C9BFF, #A78BFA, #5EE7F7, #22D3EE);
    color: #041019;
    font-size: 1.125rem;
    font-weight: 800;
    box-shadow: 0 6px 16px -6px rgba(124, 155, 255, 0.7);
}

.passport-photo {
    position: relative;
    display: flex;
    height: 7.5rem;
    width: 6rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 1px solid rgba(148, 170, 214, 0.3);
    border-radius: 0.5rem;
    background: #17223D;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--vyamap-text-subtle);
}

/* Sello holográfico sobre la esquina de la foto */
.passport-photo-seal {
    position: absolute;
    right: -0.85rem;
    bottom: -0.85rem;
    height: 2.75rem;
    width: 2.75rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background:
        repeating-conic-gradient(from 0deg, rgba(94, 231, 247, 0.55) 0 8deg, rgba(167, 139, 250, 0.55) 8deg 16deg);
    opacity: 0.55;
}

.passport-fields {
    display: grid;
    align-content: center;
    gap: 0.75rem;
    min-width: 0;
}

.passport-fields dt {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--vyamap-text-subtle);
}

.passport-fields dd {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.875rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.passport-figures {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-top: 1.5rem;
    border-top: 1px solid rgba(148, 170, 214, 0.18);
    padding-top: 1.25rem;
}

.passport-ring {
    display: flex;
    height: 4.5rem;
    width: 4.5rem;
    flex-shrink: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background:
        radial-gradient(closest-side, #0F1830 80%, transparent 81% 100%),
        conic-gradient(#22D3EE 0%, #A78BFA var(--value), rgba(148, 170, 214, 0.18) var(--value));
    font-size: 0.875rem;
    font-weight: 650;
    line-height: 1.1;
}

.passport-ring small {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--vyamap-text-subtle);
}

.passport-figure {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.passport-figure dd {
    font-size: 1.5rem;
    font-weight: 650;
    letter-spacing: -0.03em;
    line-height: 1.1;
}

.passport-figure dd small {
    margin-left: 0.125rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--vyamap-text-subtle);
}

.passport-figure dt {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--vyamap-text-subtle);
}

.passport-mrz {
    margin-top: auto;
    padding-top: 1.25rem;
    font-family: ui-monospace, 'SF Mono', Menlo, monospace;
    font-size: 0.75rem;
    letter-spacing: 0.14em;
    line-height: 1.6;
    color: rgba(166, 179, 202, 0.75);
    overflow: hidden;
    white-space: nowrap;
}

.passport-stamps {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 1.25rem;
}

@media (min-width: 480px) and (max-width: 767px) {
    .passport-stamps {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.passport-stamp {
    display: flex;
    width: 100%;
    aspect-ratio: 1.25;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    transition: background-color 0.15s var(--vyamap-ease);
}

.passport-stamp:hover,
.passport-stamp.is-active {
    background: rgba(255, 255, 255, 0.04);
}

.passport-stamp.is-cyan {
    --ink: rgba(94, 231, 247, 0.85);
}

.passport-stamp.is-violet {
    --ink: rgba(167, 139, 250, 0.9);
}

.passport-stamp-ring {
    display: flex;
    height: 92%;
    max-width: 100%;
    aspect-ratio: 1.2;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.2rem;
    border: 2px solid var(--ink);
    border-radius: 50%;
    outline: 1px dashed var(--ink);
    outline-offset: -6px;
    padding: 0.5rem 0.75rem;
    text-align: center;
    transform: rotate(var(--stamp-rotation, 0deg));
    /* Tinta: algo transparente para que se vea el papel debajo */
    opacity: 0.92;
}

.passport-stamp.is-active .passport-stamp-ring {
    opacity: 1;
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05);
}

.passport-stamp-country {
    display: -webkit-box;
    max-width: 88%;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-height: 1.15;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--ink);
}

.passport-stamp-type {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    color: var(--ink);
}

.passport-stamp-date {
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--vyamap-text);
}

.passport-empty {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    margin-block: 1.5rem;
    border: 1px dashed rgba(148, 170, 214, 0.24);
    border-radius: 1rem;
    padding: 2rem 1.25rem;
    text-align: center;
    font-size: 0.875rem;
    color: var(--vyamap-text-muted);
}

.passport-sheet-footer {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin-top: auto;
    border-top: 1px solid rgba(148, 170, 214, 0.18);
    padding-top: 1rem;
}

.passport-stamps + .passport-sheet-footer {
    margin-top: 1.5rem;
}

@media (min-width: 768px) {
    .passport-stamps + .passport-sheet-footer {
        margin-top: auto;
    }
}

.passport-dot {
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 9999px;
    background: rgba(148, 170, 214, 0.3);
    transition: width 0.15s var(--vyamap-ease), background-color 0.15s var(--vyamap-ease);
}

.passport-dot.is-active {
    width: 1.5rem;
    background: var(--vyamap-route);
}
</style>
