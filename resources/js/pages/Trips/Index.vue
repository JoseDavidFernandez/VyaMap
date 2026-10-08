<script setup lang="ts">

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

interface Country {
    name: string;
    iso_code: string;
}

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    cover: string | null;
    cities: string[];
    countries: Country[];
}

const props = defineProps<{
    trips: Trip[];
}>();

const selectedYear = ref<'all' | number>('all');

const years = computed(() =>
    [...new Set(
        props.trips
            .map((trip) =>
                trip.start_date
                    ? new Date(trip.start_date).getFullYear()
                    : null,
            )
            .filter((year): year is number => year !== null),
    )].sort((a, b) => b - a),
);

const filteredTrips = computed(() => {
    if (selectedYear.value === 'all') {
        return props.trips;
    }

    return props.trips.filter((trip) => {
        if (!trip.start_date) {
            return false;
        }

        return (
            new Date(trip.start_date).getFullYear() ===
            selectedYear.value
        );
    });
});

const totalDays = computed(() =>
    filteredTrips.value.reduce((total, trip) => {
        if (!trip.start_date || !trip.end_date) {
            return total;
        }

        const start = new Date(`${trip.start_date}T00:00:00`);
        const end = new Date(`${trip.end_date}T00:00:00`);
        const diff = end.getTime() - start.getTime();

        return total + Math.floor(diff / 86400000) + 1;
    }, 0),
);

const totalCountries = computed(() =>
    new Set(
        filteredTrips.value.flatMap((trip) =>
            trip.countries.map((country) => country.iso_code),
        ),
    ).size,
);

const totalDestinations = computed(() =>
    filteredTrips.value.reduce(
        (total, trip) => total + trip.countries.length,
        0,
    ),
);

const formatDate = (date: string | null) => {
    if (!date) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
    })
        .format(new Date(`${date}T00:00:00`))
        .toUpperCase();
};

const formatDateRange = (trip: Trip) => {
    if (!trip.start_date && !trip.end_date) {
        return '';
    }

    if (!trip.end_date) {
        return formatDate(trip.start_date);
    }

    return `${formatDate(trip.start_date)} — ${formatDate(trip.end_date)}`;
};

const tripYear = (trip: Trip) => {
    if (!trip.start_date) {
        return '';
    }

    return new Date(`${trip.start_date}T00:00:00`).getFullYear();
};

const tripDays = (trip: Trip) => {
    if (!trip.start_date || !trip.end_date) {
        return null;
    }

    const start = new Date(`${trip.start_date}T00:00:00`);
    const end = new Date(`${trip.end_date}T00:00:00`);
    const diff = end.getTime() - start.getTime();

    return Math.floor(diff / 86400000) + 1;
};

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(letter.charCodeAt(0) + 127397),
        )
        .join('');
};

const getTripGradient = (index: number) => {
    const gradients = [
        'from-cyan-300/[0.14] via-[#171b28] to-[#0d1119]',
        'from-violet-400/[0.14] via-[#171b28] to-[#0d1119]',
        'from-blue-400/[0.14] via-[#171b28] to-[#0d1119]',
        'from-emerald-300/[0.12] via-[#171b28] to-[#0d1119]',
    ];

    return gradients[index % gradients.length];
};

</script>

<template>

    <AppLayout title="Trips">

        <div class="vyamap-page">

            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >

                <!-- HEADER -->

                <section class="mb-8">

                    <div
                        class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12"
                    >

                        <div
                            class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"
                        ></div>

                        <div
                            class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"
                        ></div>

                        <div class="relative z-10">

                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Travel history
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                            >

                                <div>

                                    <h1
                                        class="text-3xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl"
                                    >
                                        Trips
                                    </h1>

                                    <p
                                        class="mt-4 max-w-xl text-sm leading-6 text-white/40 sm:mt-5 sm:text-base"
                                    >
                                        Every journey in one place.
                                    </p>

                                </div>

                                <div
                                    class="grid grid-cols-2 gap-x-6 gap-y-5 sm:flex sm:flex-wrap sm:items-end sm:gap-x-8 sm:gap-y-5"
                                >

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ filteredTrips.length }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            {{ filteredTrips.length === 1 ? 'Trip' : 'Trips' }}
                                        </div>

                                    </div>

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ totalDays }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            Days
                                        </div>

                                    </div>

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ totalCountries }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            {{ totalCountries === 1 ? 'Country' : 'Countries' }}
                                        </div>

                                    </div>

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ totalDestinations }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            Destinations
                                        </div>

                                    </div>

                                    <Link
                                        href="/trips/create"
                                        class="col-span-2 inline-flex h-10 w-full items-center justify-center rounded-xl bg-white px-4 text-xs font-medium text-[#0d1119] transition hover:bg-white/90 sm:col-auto sm:w-auto"
                                    >
                                        + New trip
                                    </Link>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- FILTER -->

                <section class="mb-7">

                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >

                        <div
                            class="flex gap-1 overflow-x-auto rounded-xl border border-white/[0.07] bg-white/[0.025] p-1"
                        >

                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs transition"
                                :class="
                                    selectedYear === 'all'
                                        ? 'bg-white text-[#0d1119]'
                                        : 'text-white/35 hover:text-white'
                                "
                                @click="selectedYear = 'all'"
                            >
                                All
                            </button>

                            <button
                                v-for="year in years"
                                :key="year"
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs transition"
                                :class="
                                    selectedYear === year
                                        ? 'bg-white text-[#0d1119]'
                                        : 'text-white/35 hover:text-white'
                                "
                                @click="selectedYear = year"
                            >
                                {{ year }}
                            </button>

                        </div>

                        <span
                            class="hidden text-[10px] uppercase tracking-[0.16em] text-white/25 sm:block"
                        >
                            {{ filteredTrips.length }}
                            {{ filteredTrips.length === 1 ? 'trip' : 'trips' }}
                        </span>

                    </div>

                </section>


                <!-- TRIPS -->

                <section>

                    <div
                        v-if="filteredTrips.length"
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >

                        <Link
                            v-for="(trip, index) in filteredTrips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group overflow-hidden rounded-[24px] border border-white/[0.07] bg-white/[0.025] transition duration-300 hover:-translate-y-1 hover:border-white/[0.14] hover:bg-white/[0.04]"
                        >

                            <!-- COVER -->

                            <div
                                class="relative aspect-[1.35] overflow-hidden bg-gradient-to-br"
                                :class="getTripGradient(index)"
                            >

                                <img
                                    v-if="trip.cover"
                                    :src="`/storage/${trip.cover}`"
                                    :alt="trip.name"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                />

                                <div
                                    v-else
                                    class="trip-placeholder"
                                >
                                    <span>
                                        {{ trip.name.charAt(0) }}
                                    </span>
                                </div>

                                <div
                                    class="absolute inset-x-0 bottom-0 flex items-end justify-between bg-gradient-to-t from-black/80 via-black/20 to-transparent p-4 pt-16"
                                >

                                    <div>

                                        <p
                                            class="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/55"
                                        >
                                            {{ formatDateRange(trip) }}
                                        </p>

                                        <h2
                                            class="mt-1 text-lg font-semibold tracking-[-0.035em] text-white"
                                        >
                                            {{ trip.name }}
                                        </h2>

                                    </div>

                                    <span
                                        class="text-xs text-white/60"
                                    >
                                        {{ tripYear(trip) }}
                                    </span>

                                </div>

                            </div>


                            <!-- INFO -->

                            <div class="p-4">

                                <p
                                    v-if="trip.countries.length"
                                    class="truncate text-xs text-white/35"
                                >
                                    {{
                                        trip.countries
                                            .map((country) => country.name)
                                            .join(' · ')
                                    }}
                                </p>

                                <p
                                    v-else
                                    class="text-xs text-white/20"
                                >
                                    No destinations recorded
                                </p>

                                <div
                                    class="mt-4 flex items-center justify-between border-t border-white/[0.07] pt-3"
                                >

                                    <div class="flex items-center gap-1">

                                        <span
                                            v-for="country in trip.countries"
                                            :key="country.iso_code"
                                            :title="country.name"
                                            class="text-base"
                                        >
                                            {{ flagEmoji(country.iso_code) }}
                                        </span>

                                    </div>

                                    <span
                                        class="text-[10px] text-white/25"
                                    >

                                        <template v-if="tripDays(trip)">
                                            {{ tripDays(trip) }} days
                                        </template>

                                        <template
                                            v-if="
                                                tripDays(trip) &&
                                                trip.cities.length
                                            "
                                        >
                                            ·
                                        </template>

                                        <template v-if="trip.cities.length">
                                            {{ trip.cities.length }}
                                            {{
                                                trip.cities.length === 1
                                                    ? 'city'
                                                    : 'cities'
                                            }}
                                        </template>

                                    </span>

                                </div>

                            </div>

                        </Link>

                    </div>


                    <!-- EMPTY STATE -->

                    <div
                        v-else
                        class="rounded-[30px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-24 text-center"
                    >

                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.03] text-xl text-white/30"
                        >
                            ◌
                        </div>

                        <h3
                            class="mt-5 text-lg font-medium tracking-[-0.03em] text-white"
                        >
                            No trips found
                        </h3>

                        <p
                            class="mx-auto mt-2 max-w-sm text-xs leading-5 text-white/30"
                        >
                            There are no trips for this period.
                        </p>

                    </div>

                </section>


                <!-- FOOTER CONTEXT -->

                <section
                    v-if="filteredTrips.length"
                    class="mt-12 border-t border-white/[0.07] pt-8"
                >

                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >

                        <div>

                            <p
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Travel archive
                            </p>

                            <h2
                                class="mt-2 text-2xl font-semibold tracking-[-0.045em] text-white"
                            >
                                Your journeys
                            </h2>

                        </div>

                        <p
                            class="max-w-md text-xs leading-5 text-white/30"
                        >
                            Each trip contains its visits, flights, photos,
                            maps and travel history.
                        </p>

                    </div>

                </section>

            </main>

        </div>

    </AppLayout>

</template>

<style scoped>

.trip-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(
            circle at 25% 20%,
            rgba(255, 255, 255, 0.09),
            transparent 32%
        ),
        radial-gradient(
            circle at 80% 75%,
            rgba(255, 255, 255, 0.045),
            transparent 35%
        ),
        var(--vyamap-surface-strong);
}

.trip-placeholder span {
    color: rgba(255, 255, 255, 0.12);
    font-size: 100px;
    font-weight: 700;
    letter-spacing: -0.08em;
}

</style>