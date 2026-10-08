<script setup lang="ts">

import AppLayout from '../layouts/AppLayout.vue';
import CountryMap from '../components/dashboard/CountryMap.vue';
import TravelMap from '../components/dashboard/TravelMap.vue';

import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface User {
    name: string;
}

interface Stats {
    countries: number;
    cities: number;
    trips: number;
    flights: number;
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
    city: {
        id: number;
        name: string;
        country: string;
        country_id: number;
        iso_code: string;
        latitude: number | null;
        longitude: number | null;
    };
    visited_from: string | null;
    visited_until: string | null;
    notes: string | null;
}

interface Flight {
    id: number;
    flight_number: string;
    airline: string | null;
    departure: string | null;
    arrival: string | null;
    origin: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };
    destination: {
        id: number;
        name: string;
        city: string;
        latitude: number;
        longitude: number;
    };
}

interface RecentTrip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    cities: string[];
    countries: string[];
    cover: string | null;
    fallbackGradient: number;
}

interface Photo {
    id: number;
    path: string;
    thumbnail_path: string | null;
    original_filename: string;
}

interface TripsByYear {
    year: number;
    count: number;
}

const props = defineProps<{
    user: User;
    stats: Stats;
    nextTrip: NextTrip | null;
    map: {
        visits: Visit[];
        flights: Flight[];
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

const formatDateTime = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
};

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
                id: visit.city.id,
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

const totalCountries: number = 251;

const topCountries = computed(() => {
    const countryMap = new Map<
        string,
        {
            id: number;
            name: string;
            iso_code: string;
            cities: string[];
            visits: number;
        }
    >();

    props.map.visits.forEach((visit) => {
        const iso = visit.city.iso_code;
        if (!iso) return;

        if (!countryMap.has(iso)) {
            countryMap.set(iso, {
                id: visit.city.country_id,
                name: visit.city.country,
                iso_code: iso,
                cities: [],
                visits: 0,
            });
        }

        const country = countryMap.get(iso)!;
        country.visits += 1;

        if (!country.cities.includes(visit.city.name)) {
            country.cities.push(visit.city.name);
        }
    });

    return Array.from(countryMap.values())
        .sort((a, b) => {
            if (b.visits !== a.visits) return b.visits - a.visits;
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

const fallbackGradients = [
    'radial-gradient(circle at 25% 20%, rgba(74, 126, 121, 0.34), transparent 42%), linear-gradient(145deg, #18272a 0%, #0b1114 100%)',
    'radial-gradient(circle at 75% 18%, rgba(92, 88, 153, 0.30), transparent 42%), linear-gradient(145deg, #1c1b2d 0%, #0d1018 100%)',
    'radial-gradient(circle at 30% 78%, rgba(139, 103, 64, 0.28), transparent 40%), linear-gradient(145deg, #29231e 0%, #111214 100%)',
];

const recentHomeTrips = computed(() => {
    let fallbackIndex = 0;

    return props.recentTrips
        .slice(0, 3)
        .map((trip) => {
            const hasCover = Boolean(trip.cover);
            const assignedGradient = hasCover ? 0 : fallbackIndex % fallbackGradients.length;

            if (!hasCover) {
                fallbackIndex += 1;
            }

            return {
                ...trip,
                fallbackGradient: assignedGradient,
            };
        });
});

const countriesPercentage = computed(() => {
    return Math.round(
        (props.stats.countries / totalCountries) * 100,
    );
});

const countriesRingStyle = computed(() => {
    const percentage = Math.min(
        100,
        Math.max(
            0,
            (props.stats.countries / totalCountries) * 100,
        ),
    );

    return {
        background: `conic-gradient(
            rgba(255,255,255,0.16) 0% ${percentage}%,
            rgba(255,255,255,0.04) ${percentage}% 100%
        )`,
    };
});

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

/*
|--------------------------------------------------------------------------
| Photos
|--------------------------------------------------------------------------
*/

const isPhotosHovered = ref(false);

const photoPositions = computed(() => {
    const count = Math.min(props.recentPhotos.length, 7);

    if (count === 0) {
        return [];
    }
    const spacing = 42;
    const center = (count - 1) / 2;

    return Array.from({ length: count }, (_, index) => {
        return (index - center) * spacing;
    });
});

const photoHoverPositions = computed(() => {
    const count = Math.min(props.recentPhotos.length, 7);

    if (count === 0) {
        return [];
    }
    const spacing = 65;
    const center = (count - 1) / 2;

    return Array.from({ length: count }, (_, index) => {
        return (index - center) * spacing;
    });

});

</script>

<template>
    <AppLayout title="Dashboard">
        <div class="vyamap-page">
            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- =====================================================
                     WELCOME / NEXT TRIP
                ====================================================== -->
                
                <section class="mb-7">
                    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12">
                        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

                        <div class="relative z-10 grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:gap-12">
                            <div class="min-w-0">
                                <div class="text-[9px] uppercase tracking-[0.24em] text-white/25">
                                    VyaMap
                                </div>

                                <h1 class="mt-3 max-w-2xl text-4xl font-semibold tracking-[-0.06em] text-white sm:text-6xl lg:text-7xl">
                                    Your travel history, in one place.
                                </h1>

                                <p class="mt-5 max-w-xl text-sm leading-6 text-white/35 sm:text-base">
                                    Build your travel history, keep your memories together and see how far you have travelled.
                                </p>
                            </div>

                            <div class="relative">
                                <div class="relative overflow-hidden rounded-[24px] border border-white/[0.08] bg-white/[0.035] px-6 py-6 shadow-2xl shadow-black/20">
                                    <div class="pointer-events-none absolute -right-16 -top-16 h-40 w-40 rounded-full bg-cyan-300/[0.06] blur-3xl"></div>
                                    <div class="pointer-events-none absolute -bottom-20 -left-16 h-40 w-40 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

                                    <div class="relative z-10">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="text-[9px] uppercase tracking-[0.24em] text-white/30">
                                                Next trip
                                            </div>

                                            <div v-if="nextTrip" class="text-[10px] uppercase tracking-[0.18em] text-cyan-300/70">
                                                {{ nextTripLabel }}
                                            </div>
                                        </div>

                                        <template v-if="nextTrip">
                                            <Link
                                                :href="`/trips/${nextTrip.id}`"
                                                class="group mt-8 block"
                                            >
                                                <h2 class="text-2xl font-semibold tracking-[-0.04em] text-white transition group-hover:text-cyan-200 sm:text-4xl">
                                                    {{ nextTrip.name }}
                                                </h2>

                                                <div class="mt-3 text-sm text-white/40">
                                                    {{ formatDate(nextTrip.start_date) }}
                                                    <span v-if="nextTrip.end_date">
                                                        — {{ formatDate(nextTrip.end_date) }}
                                                    </span>
                                                </div>

                                                <div class="mt-7 flex items-center justify-between">
                                                    <span class="text-[10px] uppercase tracking-[0.2em] text-white/25">
                                                        View trip
                                                    </span>

                                                    <span class="text-lg text-white/40 transition group-hover:translate-x-1 group-hover:text-cyan-200">
                                                        →
                                                    </span>
                                                </div>
                                            </Link>
                                        </template>

                                        <template v-else>
                                            <div class="mt-8">
                                                <h2 class="text-3xl font-semibold tracking-[-0.04em] text-white sm:text-4xl">
                                                    Nothing planned yet
                                                </h2>

                                                <p class="mt-3 max-w-sm text-sm leading-6 text-white/35">
                                                    Your next adventure will appear here.
                                                </p>

                                                <Link
                                                    href="/trips/create"
                                                    class="mt-7 inline-flex items-center gap-2 text-[10px] font-medium uppercase tracking-[0.2em] text-cyan-300/80 transition hover:text-cyan-200"
                                                >
                                                    Plan a trip
                                                    <span class="text-sm">→</span>
                                                </Link>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     STATS
                ====================================================== -->

                <section class="mb-7">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="vyamap-stat">
                            <div class="text-3xl font-semibold">
                                {{ stats.countries }}
                            </div>

                            <div class="mt-1 vyamap-eyebrow">
                                Countries
                            </div>
                        </div>

                        <div class="vyamap-stat">
                            <div class="text-3xl font-semibold">
                                {{ stats.cities }}
                            </div>

                            <div class="mt-1 vyamap-eyebrow">
                                Cities
                            </div>
                        </div>

                        <div class="vyamap-stat">
                            <div class="text-3xl font-semibold">
                                {{ stats.trips }}
                            </div>

                            <div class="mt-1 vyamap-eyebrow">
                                Trips
                            </div>
                        </div>

                        <div class="vyamap-stat">
                            <div class="text-3xl font-semibold">
                                {{ stats.flights }}
                            </div>

                            <div class="mt-1 vyamap-eyebrow">
                                Flights
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     WORLD / COUNTRIES
                ====================================================== -->
                
                <section class="mb-7 grid gap-7 lg:grid-cols-[2.35fr_1fr]" >
                    
                    <!-- WORLD MAP -->
                    <div class="vyamap-map-card overflow-hidden">
                        <div class="flex items-center justify-between border-b border-[var(--vyamap-border)] px-6 py-5 sm:px-7" >
                            <div>
                                <div class="vyamap-section-title">
                                    Exploration
                                </div>

                                <h2
                                    class="vyamap-section-heading"
                                >
                                    Your world
                                </h2>
                            </div>

                            <div class="text-[10px] vyamap-muted">
                                {{ stats.countries }} countries
                            </div>
                        </div>

                        <div class="h-[380px] w-full sm:h-[500px]">
                            <CountryMap
                                :countries="visitedCountries"
                            />
                        </div>
                    </div>
                    
                    <!-- TOP COUNTRIES -->
                    <div class="vyamap-card-lg flex flex-col p-5 sm:min-h-[585px] sm:p-7">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="vyamap-section-title">Explore</div>
                                <h2 class="vyamap-section-heading">Countries</h2>
                                <p class="mt-2 max-w-[230px] text-[10px] leading-5 text-white/30">
                                    The countries you have explored the most.
                                </p>
                            </div>

                            <Link href="/countries" class="shrink-0 pt-1 text-[10px] font-medium text-white/35 transition hover:text-white">
                                View all →
                            </Link>
                        </div>

                        <div class="mt-6 space-y-2.5">
                            <Link
                                v-for="country in topCountries"
                                :key="country.iso_code"
                                    :href="`/countries/${country.id}`"
                                class="group block rounded-[18px] border border-white/[0.07] bg-white/[0.018] p-3.5 transition duration-300 hover:border-white/[0.14] hover:bg-white/[0.035]"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.025] text-base">
                                        {{ countryFlag(country.iso_code) }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="truncate text-xs font-semibold">{{ country.name }}</h3>
                                            <span class="shrink-0 text-[9px] text-white/25">
                                                {{ country.visits }}
                                                {{ country.visits === 1 ? 'trip' : 'trips' }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <span
                                                v-for="city in country.cities.slice(0, 4)"
                                                :key="city"
                                                class="rounded-full border border-white/[0.06] px-2 py-1 text-[8px] text-white/35"
                                            >
                                                {{ city }}
                                            </span>

                                            <span
                                                v-if="country.cities.length > 4"
                                                class="rounded-full border border-white/[0.06] px-2 py-1 text-[8px] text-white/25"
                                            >
                                                +{{ country.cities.length - 4 }}
                                            </span>
                                        </div>
                                    </div>

                                    <span class="pt-0.5 text-white/15 transition group-hover:translate-x-0.5 group-hover:text-white/70">→</span>
                                </div>
                            </Link>
                        </div>

                        <div class="mt-auto pt-5">
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <div class="text-[9px] uppercase tracking-[0.18em] text-white/20">Travel footprint</div>
                                    <div class="mt-1 text-[10px] text-white/30">
                                        {{ countriesPercentage }}% of the world
                                    </div>
                                </div>

                                <div
                                    class="relative flex h-14 w-14 items-center justify-center rounded-full"
                                    :style="countriesRingStyle"
                                >
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--vyamap-surface)]">
                                        <span class="text-[11px] font-semibold">{{ countriesPercentage }}%</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2">
                                <div class="rounded-[13px] border border-white/[0.06] bg-white/[0.018] p-2.5">
                                    <div class="text-base font-semibold">{{ stats.countries }}</div>
                                    <div class="mt-0.5 text-[8px] uppercase tracking-[0.08em] text-white/25">Countries</div>
                                </div>

                                <div class="rounded-[13px] border border-white/[0.06] bg-white/[0.018] p-2.5">
                                    <div class="text-base font-semibold">{{ stats.cities }}</div>
                                    <div class="mt-0.5 text-[8px] uppercase tracking-[0.08em] text-white/25">Cities</div>
                                </div>

                                <div class="rounded-[13px] border border-white/[0.06] bg-white/[0.018] p-2.5">
                                    <div class="text-base font-semibold">{{ stats.trips }}</div>
                                    <div class="mt-0.5 text-[8px] uppercase tracking-[0.08em] text-white/25">Trips</div>
                                </div>

                                <div class="rounded-[13px] border border-white/[0.06] bg-white/[0.018] p-2.5">
                                    <div class="text-base font-semibold">{{ stats.flights }}</div>
                                    <div class="mt-0.5 text-[8px] uppercase tracking-[0.08em] text-white/25">Flights</div>
                                </div>
                            </div>

                            <Link href="/passport" class="mt-3 block text-center text-[9px] font-medium text-white/30 transition hover:text-white">
                                View all countries →
                            </Link>
                        </div>
                    </div>

                </section>

                <!-- =====================================================
                     PHOTOS
                ====================================================== -->
               
                <section class="mb-7">
                   
                    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.10] bg-gradient-to-br from-cyan-300/[0.10] via-[#171b28] to-[#0d1119] px-6 py-12 sm:px-10 sm:py-14 lg:px-16 lg:py-16">
                        
                        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.07] blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.06] blur-3xl"></div>
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <h2 class="max-w-3xl text-4xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl">
                                A place to relive your
                                <br class="hidden sm:block" />
                                travel memories.
                            </h2>

                            <div v-if="recentPhotos.length" class="relative mt-6 h-[170px] w-full max-w-[850px] sm:mt-9 sm:h-[280px]"
                                @mouseenter="isPhotosHovered = true"
                                @mouseleave="isPhotosHovered = false"
                                >
                                <div
                                    v-for="(photo, index) in recentPhotos.slice(0, 7)"
                                    :key="photo.id"
                                    class="absolute left-1/2 top-1/2 h-[150px] w-[110px] overflow-hidden rounded-[14px] border-[4px] border-white bg-white shadow-2xl transition-all duration-500 ease-out sm:h-[250px] sm:w-[200px] sm:rounded-[20px] sm:border-[5px]"
                                    :style="{
                                        zIndex: 20 - index,
                                        transform: `translate(-50%, -50%) translateX(${
                                            isPhotosHovered
                                                ? photoHoverPositions[index]
                                                : photoPositions[index]
                                        }px) translateY(${
                                            isPhotosHovered
                                                ? [14, 6, -2, -7, -2, 6, 14][index]
                                                : [12, 6, 0, -4, 0, 6, 12][index]
                                        }px) rotate(${
                                            [-8, -5, -2, 0, 2, 5, 8][index]
                                        }deg)`
                                    }"
                                >

                                    <img

                                        :src="`/storage/${photo.thumbnail_path ?? photo.path}`"

                                        :alt="photo.original_filename"

                                        class="h-full w-full object-cover"

                                        loading="lazy"

                                    />

                                </div>

                            </div>

                            <div v-else class="flex h-[190px] items-center justify-center sm:h-[250px]">
                                <div class="text-sm text-white/30">
                                    Your visual travel history will appear here.
                                </div>
                            </div>

                            <p class="mt-5 max-w-xl text-xs leading-5 text-white/45 sm:text-sm">
                                Keep the places you've visited close.
                                <br class="hidden sm:block" />
                                Your journeys, captured in one place.
                            </p>

                            <div class="mt-6 flex items-center justify-center gap-6">
                                <Link
                                    href="/photos"
                                    class="rounded-full bg-white px-5 py-2.5 text-[11px] font-medium text-[#11151c] transition hover:bg-white/90"
                                >
                                    View all photos
                                </Link>

                                <Link
                                    href="/photos"
                                    class="text-[11px] font-medium text-white/50 transition hover:text-white"
                                >
                                    Explore memories →
                                </Link>

                            </div>

                        </div>

                    </div>

                </section>

                 <!-- =====================================================
                     RECENT TRIPS
                ====================================================== -->

                <section class="mb-7">
                    <div class="mb-6 flex items-end justify-between gap-4">
                        <div>
                            <div class="vyamap-section-title">History</div>
                            <h2 class="vyamap-section-heading">Recent trips</h2>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="text-[10px] text-white/25">Showing 3</span>
                            <Link href="/trips" class="vyamap-link text-xs">View all →</Link>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="trip in recentHomeTrips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group overflow-hidden rounded-[26px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] transition hover:border-[var(--vyamap-border-strong)] lg:grid lg:h-[220px] lg:grid-cols-[260px_1fr]"
                        >
                            <div class="relative h-[180px] overflow-hidden border-b border-[var(--vyamap-border)] bg-[#151b20] lg:h-full lg:border-b-0 lg:border-r">

                                <img
                                    v-if="tripCoverUrl(trip)"
                                    :src="tripCoverUrl(trip)!"
                                    :alt="trip.name"
                                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                />

                                <div
                                    v-else
                                    class="absolute inset-0 overflow-hidden"
                                    :style="{ background: fallbackGradients[trip.fallbackGradient] }"
                                >
                                    <div class="absolute -right-8 -top-10 h-32 w-32 rounded-full border border-white/[0.05]"></div>
                                    <div class="absolute -bottom-16 -left-8 h-40 w-40 rounded-full border border-white/[0.04]"></div>

                                    <div class="absolute inset-0 flex flex-col justify-between p-5">
                                        <span class="text-3xl opacity-80"></span>

                                        <div>
                                            <div class="text-[8px] uppercase tracking-[0.22em] text-white/25">
                                                {{ trip.countries.join(' · ') }}
                                            </div>
                                            <div class="mt-1 text-xs text-white/45">
                                                {{ trip.cities.slice(0, 3).join(' · ') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="tripCoverUrl(trip)"
                                    class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent"
                                ></div>
                            </div>

                            <div class="p-6 sm:p-7">
                                <div class="flex items-start justify-between gap-6">
                                    <div>
                                        <div class="text-[9px] uppercase tracking-[0.22em] text-white/25">
                                            {{ trip.countries.join(' · ') }}
                                        </div>

                                        <h3 class="mt-2 text-xl font-semibold tracking-[-0.045em] transition group-hover:text-white/80 sm:text-2xl">
                                            {{ trip.name }}
                                        </h3>

                                        <p class="mt-2 text-[10px] text-white/30">
                                            {{ formatDate(trip.start_date) }}
                                            <span v-if="trip.end_date"> — {{ formatDate(trip.end_date) }}</span>
                                        </p>
                                    </div>

                                    <span class="text-[10px] text-white/25">
                                        {{ trip.cities.length }}
                                        {{ trip.cities.length === 1 ? 'city' : 'cities' }}
                                    </span>
                                </div>

                                <div v-if="trip.cities.length" class="mt-10">
                                    <div class="mb-2 text-[8px] uppercase tracking-[0.18em] text-white/20">Places</div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="city in trip.cities.slice(0, 4)"
                                            :key="city"
                                            class="rounded-full border border-white/[0.07] px-3 py-1.5 text-[10px] text-white/40"
                                        >
                                            {{ city }}
                                        </span>

                                        <span
                                            v-if="trip.cities.length > 4"
                                            class="rounded-full border border-white/[0.07] px-3 py-1.5 text-[10px] text-white/25"
                                        >
                                            +{{ trip.cities.length - 4 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Link>

                        <div
                            v-if="!recentTrips.length"
                            class="rounded-[24px] border border-dashed border-white/[0.10] p-12 text-center text-sm text-white/30"
                        >
                            No trips recorded yet.
                        </div>
                    </div>
                </section>

                 <!-- =====================================================
                     TRIPS BY YEAR / AT A GLANCE
                ====================================================== -->

                <section class="mb-7 grid gap-7 lg:grid-cols-[1fr_1fr]">
                    <div class="vyamap-card-lg p-6 sm:p-7">
                        <div class="vyamap-section-title">History</div>
                        <h2 class="vyamap-section-heading">Trips by year</h2>

                        <div v-if="tripsByYear.length" class="mt-8 space-y-6">
                            <div v-for="item in tripsByYear" :key="item.year">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ item.year }}</span>
                                    <span class="text-xs vyamap-muted">
                                        {{ item.count }} {{ item.count === 1 ? 'trip' : 'trips' }}
                                    </span>
                                </div>

                                <div class="h-2 overflow-hidden rounded-full bg-white/[0.05]">
                                    <div
                                        class="h-full rounded-full bg-white/30 transition-all"
                                        :style="{ width: `${(item.count / maxTripsByYear) * 100}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="vyamap-card-lg p-6 sm:p-7">
                        <div class="vyamap-section-title">Overview</div>
                        <h2 class="vyamap-section-heading">At a glance</h2>

                        <div class="mt-7 grid grid-cols-2 gap-3">
                            <div class="vyamap-card p-5">
                                <div class="vyamap-eyebrow">Countries</div>
                                <div class="mt-2 text-2xl font-semibold">{{ stats.countries }}</div>
                            </div>
                            <div class="vyamap-card p-5">
                                <div class="vyamap-eyebrow">Cities</div>
                                <div class="mt-2 text-2xl font-semibold">{{ stats.cities }}</div>
                            </div>
                            <div class="vyamap-card p-5">
                                <div class="vyamap-eyebrow">Trips</div>
                                <div class="mt-2 text-2xl font-semibold">{{ stats.trips }}</div>
                            </div>
                            <div class="vyamap-card p-5">
                                <div class="vyamap-eyebrow">Flights</div>
                                <div class="mt-2 text-2xl font-semibold">{{ stats.flights }}</div>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-[var(--vyamap-border)] pt-5">
                            <div class="flex items-center justify-between gap-5">
                                <div>
                                    <div class="text-xs font-medium">Travel footprint</div>
                                    <div class="mt-1 text-[10px] vyamap-text-subtle">
                                        {{ countriesPercentage }}% of countries visited
                                    </div>
                                </div>

                                <Link href="/passport" class="text-[10px] text-white/35 transition hover:text-white">
                                    Passport →
                                </Link>
                            </div>

                            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/[0.05]">
                                <div
                                    class="h-full rounded-full bg-white/30"
                                    :style="{ width: `${Math.min(countriesPercentage, 100)}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>

                 <!-- =====================================================
                     FLIGHT HISTORY
                ====================================================== -->

                <section>
                    <div class="grid gap-7 lg:grid-cols-[0.9fr_2fr]">
                        <div class="vyamap-card-lg p-6 sm:p-7">
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <div class="vyamap-section-title">Transport</div>
                                    <h2 class="vyamap-section-heading">Flights</h2>
                                </div>

                                <Link href="/flight-history" class="text-[10px] text-white/35 hover:text-white">
                                    Full history →
                                </Link>
                            </div>

                            <div class="mt-7 space-y-3">
                                <div v-for="flight in recentFlights" :key="flight.id" class="vyamap-flight">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="text-sm font-semibold">{{ flight.flight_number }}</div>
                                            <div v-if="flight.airline" class="mt-1 text-[10px] vyamap-muted">
                                                {{ flight.airline }}
                                            </div>
                                        </div>

                                        <div class="text-right text-[10px] vyamap-text-subtle">
                                            {{ flight.origin.city }} → {{ flight.destination.city }}
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-4 border-t border-[var(--vyamap-border)] pt-3">
                                        <div>
                                            <div class="vyamap-eyebrow">Departure</div>
                                            <div class="mt-1 text-[10px] text-white/45">
                                                {{ formatDateTime(flight.departure) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="vyamap-eyebrow">Arrival</div>
                                            <div class="mt-1 text-[10px] text-white/45">
                                                {{ formatDateTime(flight.arrival) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="vyamap-map-card overflow-hidden">
                            <div class="flex items-center justify-between border-b border-[var(--vyamap-border)] px-6 py-5 sm:px-7">
                                <div>
                                    <div class="vyamap-section-title">Routes</div>
                                    <h2 class="vyamap-section-heading">Flight map</h2>
                                </div>

                                <span class="text-[10px] vyamap-text-subtle">
                                    {{ stats.flights }} flights
                                </span>
                            </div>

                            <div class="h-[380px] w-full sm:h-[500px]">
                                <TravelMap :cities="[]" :flights="map.flights" />
                            </div>
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </AppLayout>
</template>
