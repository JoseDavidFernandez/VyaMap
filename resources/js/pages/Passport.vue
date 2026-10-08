<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../layouts/AppLayout.vue';
import CountryMap from '../components/dashboard/CountryMap.vue';

interface Visit {
    id: number;
    city_id: number;
    city: string;
    country: string;
    iso_code: string;
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

    return props.visits.filter((visit) => {
        if (!visit.visited_from) {
            return false;
        }

        return (
            new Date(
                visit.visited_from,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
});

const periodTrips = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.trips;
    }

    return props.trips.filter((trip) => {
        if (!trip.start_date) {
            return false;
        }

        return (
            new Date(
                trip.start_date,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
});

const periodFlights = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.flights;
    }

    return props.flights.filter((flight) => {
        if (!flight.departure) {
            return false;
        }

        return (
            new Date(
                flight.departure,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
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

    periodVisits.value.forEach((visit) => {
        if (!countries.has(visit.iso_code)) {
            countries.set(visit.iso_code, {
                id: visit.city_id,
                name: visit.country,
                iso_code: visit.iso_code,
            });
        }
    });

    return Array.from(
        countries.values(),
    ).sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});

const visitedCities = computed(() => {
    return new Set(
        periodVisits.value.map(
            (visit) => visit.city_id,
        ),
    ).size;
});

const countriesPercentage = computed(() => {
    if (!props.totalCountries) {
        return 0;
    }

    return (
        (visitedCountries.value.length /
            props.totalCountries) *
        100
    );
});

const countriesRingStyle = computed(() => {
    const percentage = Math.min(
        100,
        countriesPercentage.value,
    );

    return {
        background: `conic-gradient(
            var(--vyamap-text) ${percentage}%,
            var(--vyamap-surface-strong) ${percentage}% 100%
        )`,
    };
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
        Math.min(100, (visitedCountries.value.length / props.totalCountries) * 100).toFixed(1),
    );
});

const passportTotalDistance = computed(() =>
    periodFlights.value.reduce((total, flight) => total + flight.distance_km, 0),
);

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

const tripsByYear = computed(() => {
    const groups = new Map<
        number,
        number
    >();

    periodTrips.value.forEach((trip) => {
        if (!trip.start_date) {
            return;
        }

        const year = new Date(
            trip.start_date,
        ).getFullYear();
        groups.set(
            year,
            (groups.get(year) ?? 0) + 1,
        );
    });

    return Array.from(
        groups.entries(),
    )
        .map(([year, count]) => ({
            year,
            count,
        }))
        .sort((a, b) => b.year - a.year);
});

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
        },
    ).format(new Date(date));

};

</script>

<template>
    <AppLayout title="Passport">
        <div class="vyamap-page">
            <div
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
                                        Passport
                                    </h1>

                                    <p class="mt-4 max-w-2xl text-sm leading-6 text-white/35 sm:text-base">
                                        A visual record of the places you have visited and the journeys you have taken.
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
                                            v-for="year in props.years"
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
                     PASSPORT
                ====================================================== -->

                <section class="mb-8">
                    <div class="passport-heading">
                        <div>
                            <p class="passport-eyebrow">
                                Personal travel document
                            </p>
                            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.045em]">
                                VyaMap Passport
                            </h2>
                        </div>

                        <div class="passport-page-control">
                            <button
                                type="button"
                                class="passport-arrow"
                                :disabled="activePassportPage === 1"
                                @click="goToPassportPage(activePassportPage - 1)"
                            >
                                ←
                            </button>

                            <span>
                                Page {{ activePassportPage }} / {{ passportTotalPages }}
                            </span>

                            <button
                                type="button"
                                class="passport-arrow"
                                :disabled="activePassportPage === passportTotalPages"
                                @click="goToPassportPage(activePassportPage + 1)"
                            >
                                →
                            </button>
                        </div>
                    </div>

                    <div class="passport-book">
                        <!-- LEFT PAGE -->

                        <div class="passport-page-left">
                            <div class="passport-watermark">V</div>

                            <div class="passport-page-content">
                                <div class="flex items-start justify-between gap-5">
                                    <div>
                                        <p class="passport-document-label">
                                            TRAVEL DOCUMENT
                                        </p>
                                        <h3 class="mt-2 text-2xl font-semibold tracking-[-0.05em]">
                                            {{ userName }}
                                        </h3>
                                    </div>

                                    <div class="passport-emblem">
                                        <span>V</span>
                                    </div>
                                </div>

                                <div class="passport-photo-area">
                                    <div class="passport-photo-placeholder">
                                        <img
                                            v-if="userAvatar"
                                            :src="userAvatar"
                                            :alt="userName"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>
                                            {{ userName.charAt(0).toUpperCase() }}
                                        </span>
                                    </div>

                                    <div class="passport-identity">
                                        <div>
                                            <span>Document</span>
                                            <strong>TRAVEL PASSPORT</strong>
                                        </div>

                                        <div>
                                            <span>Passport No.</span>
                                            <strong>VYM-00001</strong>
                                        </div>

                                        <div>
                                            <span>Issued</span>
                                            <strong>2023</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="passport-name">
                                    <span>Holder</span>
                                    <strong>{{ userName.toUpperCase() }}</strong>
                                </div>

                                <!-- GRAPHICAL STATS -->

                                <div class="passport-stat-grid">
                                    <div class="passport-stat-ring">
                                        <svg viewBox="0 0 42 42">
                                            <circle
                                                cx="21"
                                                cy="21"
                                                r="17"
                                                fill="none"
                                                stroke="rgba(38,35,29,.12)"
                                                stroke-width="3"
                                            />
                                            <circle
                                                cx="21"
                                                cy="21"
                                                r="17"
                                                fill="none"
                                                stroke="rgba(61,75,68,.62)"
                                                stroke-width="3"
                                                stroke-linecap="round"
                                                :stroke-dasharray="`${passportCountriesPercentage} ${100 - passportCountriesPercentage}`"
                                                stroke-dashoffset="25"
                                            />
                                        </svg>

                                        <div>
                                            <strong>{{ passportCountriesPercentage }}%</strong>
                                            <span>world</span>
                                        </div>
                                    </div>

                                    <div class="passport-stat">
                                        <strong>{{ visitedCountries.length }}</strong>
                                        <span>/ {{ props.totalCountries }}</span>
                                        <small>countries</small>
                                    </div>

                                    <div class="passport-stat">
                                        <strong>{{ visitedCities }}</strong>
                                        <small>cities</small>
                                    </div>

                                    <div class="passport-stat">
                                        <strong>{{ periodTrips.length }}</strong>
                                        <small>trips</small>
                                    </div>
                                </div>

                                <div class="passport-mrz">
                                    <div>VYAMAP&lt;&lt;TRAVEL&lt;&lt;PASSPORT&lt;&lt;&lt;&lt;&lt;&lt;&lt;</div>
                                    <div>VYM00001&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;&lt;2023</div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT PAGE -->

                        <div class="passport-page-right">
                            <div class="passport-page-header">
                                <div>
                                    <p class="passport-document-label">
                                        VISAS &amp; STAMPS
                                    </p>
                                    <h3 class="mt-2 text-xl font-semibold tracking-[-0.04em]">
                                        Places visited
                                    </h3>
                                </div>

                                <div class="passport-page-number">
                                    {{ String(activePassportPage).padStart(2, '0') }}
                                </div>
                            </div>

                            <div class="stamp-grid">
                                <button
                                    v-for="stamp in currentPassportStamps"
                                    :key="stamp.id"
                                    type="button"
                                    class="passport-stamp"
                                    :class="{ 'passport-stamp-active': activePassportStamp?.id === stamp.id }"
                                    :style="{ '--stamp-rotation': stamp.rotation }"
                                    @click="selectPassportStamp(stamp)"
                                >
                                    <span class="stamp-ring">
                                        <span class="stamp-country">{{ stamp.country }}</span>
                                        <span class="stamp-flag">{{ flagEmoji(stamp.iso_code) }}</span>
                                        <span class="stamp-type">{{ stampLabel(stamp) }}</span>
                                        <span class="stamp-date">{{ formatPassportDate(stamp.date) }}</span>
                                    </span>
                                </button>

                                <div
                                    v-if="!currentPassportStamps.length"
                                    class="passport-empty-page"
                                >
                                    <span>✦</span>
                                    <p>More journeys will appear here.</p>
                                </div>
                            </div>

                            <div class="passport-page-footer">
                                <div>
                                    <span>VALID FOR</span>
                                    <strong>PERSONAL TRAVEL HISTORY</strong>
                                </div>

                                <div class="passport-footer-code">
                                    VYM / {{ String(activePassportPage).padStart(2, '0') }}
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <div class="passport-page-dots">
                        <button
                            v-for="page in passportTotalPages"
                            :key="page"
                            type="button"
                            :class="{ active: activePassportPage === page }"
                            @click="goToPassportPage(page)"
                        ></button>
                    </div>

                    <Transition name="passport-detail">
                        <div
                            v-if="activePassportStamp"
                            class="passport-stamp-detail"
                        >
                            <div>
                                <p class="passport-eyebrow">
                                    Selected stamp
                                </p>

                                <div class="mt-2 flex items-center gap-3">
                                    <span class="text-2xl">
                                        {{ flagEmoji(activePassportStamp.iso_code) }}
                                    </span>

                                    <h3 class="text-lg font-semibold">
                                        {{ activePassportStamp.country }}
                                    </h3>
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="passport-eyebrow">
                                    {{ stampLabel(activePassportStamp) }}
                                </p>
                                <p class="mt-2 text-sm font-medium">
                                    {{ formatPassportDate(activePassportStamp.date) }}
                                </p>
                            </div>
                        </div>
                    </Transition>
                </section>

                <!-- =====================================================
                     WORLD MAP
                ====================================================== -->

                <section class="mt-8">
                    <div class="passport-map-shell mb-7">
                        <div class="flex flex-col gap-3 px-6 pt-6 sm:px-8 sm:pt-7 md:flex-row md:items-end md:justify-between">
                            <div>
                                <p class="passport-eyebrow">
                                    World explored
                                </p>
                                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                    Your map
                                </h2>
                            </div>

                            <div class="passport-map-counter">
                                <span>{{ visitedCountries.length }}</span>
                                / {{ props.totalCountries }} countries explored
                            </div>
                        </div>

                        <div class="passport-map">
                            <CountryMap :countries="visitedCountries" />
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     INFO
                ====================================================== -->

                <section class="mt-8">
                    <div class="analytics-card">
                        <div>
                            <p class="passport-eyebrow">Travel analytics</p>
                            <h2 class="mt-2 text-2xl font-semibold tracking-[-0.045em]">
                                At a glance
                            </h2>
                        </div>

                        <div class="analytics-grid">
                            <div class="analytics-item">
                                <span>Countries</span>
                                <strong>{{ visitedCountries.length }} / {{ props.totalCountries }}</strong>
                                <div class="analytics-bar">
                                    <div :style="{ width: `${passportCountriesPercentage}%` }"></div>
                                </div>
                            </div>

                            <div class="analytics-item">
                                <span>Cities</span>
                                <strong>{{ visitedCities }}</strong>
                                <div class="analytics-bars">
                                    <i
                                        v-for="n in 8"
                                        :key="n"
                                        :class="{ filled: n <= Math.min(8, Math.ceil(visitedCities / 10)) }"
                                    ></i>
                                </div>
                            </div>

                            <div class="analytics-item">
                                <span>Trips</span>
                                <strong>{{ periodTrips.length }}</strong>
                                <div class="analytics-bars">
                                    <i
                                        v-for="n in 8"
                                        :key="n"
                                        :class="{ filled: n <= Math.min(8, Math.ceil(periodTrips.length / 2)) }"
                                    ></i>
                                </div>
                            </div>

                            <div class="analytics-item">
                                <span>Flights</span>
                                <strong>{{ periodFlights.length }}</strong>
                                <div class="analytics-distance">
                                    {{ formatNumber(passportTotalDistance) }} km
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     RECENT VISITS + TRIPS BY YEAR
                ====================================================== -->

                <section class="mt-8">
                    <div class="grid gap-7 lg:grid-cols-[0.8fr_1.2fr]">
                        <!-- RECENT VISITS -->
                        <div class="vyamap-card-lg p-6 sm:p-8">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="vyamap-section-title">Recent</p>
                                    <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">Últimos visitados</h2>
                                </div>
                                <span class="text-xs vyamap-muted">{{ recentVisits.length }}</span>
                            </div>

                            <div
                                v-if="recentVisits.length"
                                class="mt-7 divide-y divide-[var(--vyamap-border)]"
                            >
                                <div
                                    v-for="visit in recentVisits"
                                    :key="visit.id"
                                    class="flex items-center gap-3 py-4 first:pt-0 last:pb-0"
                                >
                                    <span class="text-2xl leading-none">{{ flagEmoji(visit.iso_code) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium">{{ visit.city }}</div>
                                        <div class="mt-1 truncate text-xs vyamap-muted">{{ visit.country }}</div>
                                    </div>
                                    <div class="shrink-0 text-right text-[11px] vyamap-text-subtle">{{ formatDate(visit.visited_from) }}</div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-7 border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <p class="text-sm vyamap-text-subtle">No visits recorded yet.</p>
                            </div>
                        </div>

                        <!-- TRIPS BY YEAR -->
                        <div class="vyamap-card-lg p-6 sm:p-8">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="vyamap-section-title">Travel history</p>
                                    <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">Trips by year</h2>
                                </div>
                                <span class="text-xs vyamap-muted">{{ periodTrips.length }} trips</span>
                            </div>

                            <div v-if="tripsByYear.length" class="mt-9 space-y-6">
                                <div v-for="item in tripsByYear" :key="item.year">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm font-medium">{{ item.year }}</span>
                                        <span class="text-xs vyamap-muted">
                                            {{ item.count }} {{ item.count === 1 ? 'trip' : 'trips' }}
                                        </span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)]">
                                        <div
                                            class="h-full rounded-full bg-[var(--vyamap-text)] transition-all duration-500"
                                            :style="{ width: `${(item.count / maxTripsByYear) * 100}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-7 border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <p class="text-sm vyamap-text-subtle">No trips recorded yet.</p>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

        </div>

    </AppLayout>

</template>

<style scoped>

.passport-page {
    min-height: 100vh;
    color: #f4f4f2;
}

.passport-eyebrow,
.passport-document-label {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.24em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.34);
}

.passport-intro {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 32px;
    padding: 30px 34px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 22px;
    background:
        radial-gradient(circle at 85% 15%, rgba(90, 110, 92, 0.09), transparent 34%),
        rgba(255, 255, 255, 0.025);
}

.passport-period {
    display: flex;
    gap: 5px;
    padding: 5px;
    overflow-x: auto;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.14);
    white-space: nowrap;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.34);
}

.passport-period span {
    padding: 8px 13px;
    border-radius: 999px;
}

.passport-period-active {
    background: rgba(255, 255, 255, 0.09);
    color: #fff;
}

.passport-map-shell {
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    background:
        radial-gradient(circle at 50% 20%, rgba(83, 107, 105, 0.1), transparent 45%),
        #10191c;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.16);
}

.passport-map-counter {
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.35);
}

.passport-map-counter span {
    margin-right: 5px;
    color: rgba(245, 239, 219, 0.9);
    font-size: 20px;
    font-weight: 600;
    letter-spacing: -0.04em;
}


.passport-map {
    height: 540px;
    margin-top: 18px;
    overflow: hidden;
}


.passport-map :deep(.leaflet-container) {
    background: #10191c;
}


.passport-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin: 0 auto 14px;
    max-width: 930px;
}

.passport-page-control {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.32);
}

.passport-arrow {
    display: flex;
    height: 29px;
    width: 29px;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.025);
    color: rgba(255, 255, 255, 0.65);
    transition: 160ms ease;
}

.passport-arrow:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
}

.passport-arrow:disabled {
    cursor: default;
    opacity: 0.25;
}

.passport-book {
    position: relative;
    display: grid;
    grid-template-columns: 1fr 1fr;
    width: min(100%, 930px);
    min-height: 570px;
    margin: 0 auto;
    overflow: hidden;
    border: 1px solid rgba(42, 35, 25, 0.25);
    border-radius: 18px;
    background: #c9c1aa;
    box-shadow:
        0 35px 80px rgba(0, 0, 0, 0.24),
        inset 0 0 0 1px rgba(45, 38, 26, 0.16);
}

.passport-book::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 10;
    background:
        repeating-linear-gradient(
            0deg,
            rgba(40, 32, 20, 0.025) 0,
            rgba(40, 32, 20, 0.025) 1px,
            transparent 1px,
            transparent 4px
        );
    mix-blend-mode: multiply;
}

.passport-page-left,
.passport-page-right {
    position: relative;
    overflow: hidden;
}

.passport-page-left {
    border-right: 1px solid rgba(42, 35, 25, 0.22);
    background:
        radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.28), transparent 25%),
        linear-gradient(135deg, #d4cdbb 0%, #c6bea9 100%);
    color: #26231d;
}

.passport-page-right {
    background:
        radial-gradient(circle at 70% 15%, rgba(255, 255, 255, 0.24), transparent 24%),
        linear-gradient(135deg, #d0c8b4 0%, #c3baa4 100%);
    color: #26231d;
}

.passport-page-content {
    position: relative;
    z-index: 2;
    height: 100%;
    padding: 31px;
}

.passport-watermark {
    position: absolute;
    top: 40%;
    left: 50%;
    z-index: 1;
    transform: translate(-50%, -50%);
    color: rgba(66, 79, 72, 0.055);
    font-size: 300px;
    font-weight: 800;
    line-height: 1;
}

.passport-emblem {
    display: flex;
    height: 48px;
    width: 48px;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(38, 35, 29, 0.32);
    border-radius: 50%;
    color: rgba(38, 35, 29, 0.68);
    font-size: 20px;
    font-weight: 700;
}

.passport-photo-area {
    position: relative;
    z-index: 2;
    display: flex;
    gap: 20px;
    margin-top: 35px;
}

.passport-photo-placeholder {
    display: flex;
    height: 120px;
    width: 94px;
    flex-shrink: 0;
    overflow: hidden;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(38, 35, 29, 0.24);
    background: rgba(50, 55, 50, 0.08);
    color: rgba(38, 35, 29, 0.24);
    font-size: 40px;
    font-weight: 700;
}

.passport-identity {
    display: grid;
    align-content: center;
    gap: 14px;
}

.passport-identity span,
.passport-name span,
.passport-stat span {
    display: block;
    margin-bottom: 3px;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: rgba(38, 35, 29, 0.48);
}

.passport-identity strong,
.passport-name strong {
    font-size: 9px;
    letter-spacing: 0.06em;
}

.passport-name {
    position: relative;
    z-index: 2;
    margin-top: 25px;
    padding-top: 14px;
    border-top: 1px solid rgba(38, 35, 29, 0.18);
}

.passport-stat-grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1.25fr 1fr 1fr 1fr;
    align-items: center;
    gap: 13px;
    margin-top: 27px;
    padding-top: 18px;
    border-top: 1px solid rgba(38, 35, 29, 0.18);
}

.passport-stat-ring {
    position: relative;
    height: 64px;
    width: 64px;
}

.passport-stat-ring svg {
    height: 100%;
    width: 100%;
    transform: rotate(-90deg);
}

.passport-stat-ring > div {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.passport-stat-ring strong {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: -0.03em;
}

.passport-stat-ring span {
    margin: 0;
    font-size: 6px;
}

.passport-stat strong {
    display: block;
    font-size: 22px;
    font-weight: 600;
    letter-spacing: -0.05em;
}

.passport-stat small {
    display: block;
    margin-top: 1px;
    font-size: 7px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(38, 35, 29, 0.48);
}

.passport-mrz {
    position: absolute;
    right: 31px;
    bottom: 27px;
    left: 31px;
    z-index: 2;
    display: grid;
    gap: 3px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 7px;
    letter-spacing: 0.16em;
    color: rgba(38, 35, 29, 0.48);
}

.passport-page-header {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 31px 31px 0;
}

.passport-page-number {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    letter-spacing: 0.2em;
    color: rgba(38, 35, 29, 0.38);
}

.stamp-grid {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-content: start;
    gap: 18px 14px;
    padding: 27px 25px 65px;
}

.passport-stamp {
    position: relative;
    min-height: 124px;
    border: 0;
    background: transparent;
    color: rgba(54, 67, 61, 0.62);
    transform: rotate(var(--stamp-rotation));
    transition: transform 180ms ease, opacity 180ms ease;
    opacity: 0.72;
    cursor: pointer;
}

.passport-stamp:hover,
.passport-stamp-active {
    opacity: 1;
    transform: rotate(0deg) scale(1.035);
}

.stamp-ring {
    position: relative;
    display: flex;
    height: 100%;
    min-height: 124px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid currentColor;
    border-radius: 48%;
    padding: 12px;
}

.stamp-ring::before,
.stamp-ring::after {
    content: '';
    position: absolute;
    inset: 6px;
    border: 1px dashed currentColor;
    border-radius: 46%;
}

.stamp-ring::after {
    inset: 13px;
    border-style: dotted;
    opacity: 0.45;
}

.stamp-country,
.stamp-type,
.stamp-date,
.stamp-flag {
    position: relative;
    z-index: 2;
}

.stamp-country {
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.stamp-flag {
    margin-top: 4px;
    font-size: 17px;
    filter: grayscale(0.55);
}

.stamp-type {
    margin-top: 3px;
    font-size: 6px;
    font-weight: 800;
    letter-spacing: 0.18em;
}

.stamp-date {
    margin-top: 5px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 6px;
    letter-spacing: 0.1em;
}

.passport-empty-page {
    display: flex;
    min-height: 260px;
    grid-column: 1 / -1;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    color: rgba(38, 35, 29, 0.32);
    font-size: 11px;
}

.passport-empty-page span {
    font-size: 22px;
}

.passport-page-footer {
    position: absolute;
    right: 25px;
    bottom: 25px;
    left: 25px;
    z-index: 3;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    padding-top: 13px;
    border-top: 1px solid rgba(38, 35, 29, 0.18);
}

.passport-page-footer span {
    display: block;
    font-size: 6px;
    font-weight: 800;
    letter-spacing: 0.18em;
    color: rgba(38, 35, 29, 0.42);
}

.passport-page-footer strong {
    display: block;
    margin-top: 3px;
    font-size: 7px;
    letter-spacing: 0.08em;
}

.passport-footer-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 7px;
    letter-spacing: 0.18em;
    color: rgba(38, 35, 29, 0.42);
}

.passport-page-dots {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 14px;
}

.passport-page-dots button {
    height: 5px;
    width: 5px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.18);
    transition: 160ms ease;
}

.passport-page-dots button.active {
    width: 18px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.65);
}

.passport-stamp-detail {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    width: min(100%, 930px);
    margin: 12px auto 0;
    padding: 15px 19px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.025);
}

.passport-detail-enter-active,
.passport-detail-leave-active {
    transition: all 180ms ease;
}

.passport-detail-enter-from,
.passport-detail-leave-to {
    opacity: 0;
    transform: translateY(-5px);
}

.analytics-card {
    display: grid;
    grid-template-columns: 0.55fr 1.45fr;
    gap: 40px;
    padding: 27px 30px;
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.025);
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 22px;
}

.analytics-item {
    min-width: 0;
}

.analytics-item > span {
    display: block;
    font-size: 8px;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.32);
}

.analytics-item strong {
    display: block;
    margin-top: 5px;
    font-size: 23px;
    font-weight: 600;
    letter-spacing: -0.045em;
}

.analytics-bar {
    height: 4px;
    margin-top: 12px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
}

.analytics-bar div {
    height: 100%;
    border-radius: inherit;
    background: rgba(230, 222, 196, 0.7);
}

.analytics-bars {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 18px;
    margin-top: 10px;
}

.analytics-bars i {
    display: block;
    width: 5px;
    height: 7px;
    border-radius: 2px;
    background: rgba(255, 255, 255, 0.09);
}

.analytics-bars i:nth-child(2) { height: 10px; }
.analytics-bars i:nth-child(3) { height: 8px; }
.analytics-bars i:nth-child(4) { height: 13px; }
.analytics-bars i:nth-child(5) { height: 11px; }
.analytics-bars i:nth-child(6) { height: 16px; }
.analytics-bars i:nth-child(7) { height: 12px; }
.analytics-bars i:nth-child(8) { height: 17px; }
.analytics-bars i.filled {
    background: rgba(230, 222, 196, 0.65);
}

.analytics-distance {
    margin-top: 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 8px;
    color: rgba(255, 255, 255, 0.34);
}

@media (max-width: 900px) {
    .passport-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .passport-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .passport-book {
        grid-template-columns: 1fr;
    }

    .passport-page-left {
        border-right: 0;
        border-bottom: 1px solid rgba(42, 35, 25, 0.22);
    }

    .analytics-card {
        grid-template-columns: 1fr;
    }

    .analytics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 560px) {
    .passport-map {
        height: 390px;
    }

    .passport-page-content,
    .passport-page-header {
        padding: 24px;
    }

    .stamp-grid {
        gap: 14px 8px;
        padding: 22px 14px 62px;
    }

    .passport-stamp,
    .stamp-ring {
        min-height: 112px;
    }

    .passport-stat-grid {
        grid-template-columns: 1.2fr 1fr 1fr;
    }

    .passport-stat:last-child {
        display: none;
    }

    .passport-stamp-detail {
        align-items: flex-start;
        flex-direction: column;
    }
}

</style>


