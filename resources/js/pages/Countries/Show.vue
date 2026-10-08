<script setup lang="ts">

import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';

interface Country {
    id: number;
    name: string;
    iso_code: string;
}

interface City {
    id: number;
    name: string;
    visits_count: number;
}

interface Trip {
    id: number;
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

    <AppLayout :title="country.name">

        <div class="vyamap-page">

            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >

                <!-- BACK -->

                <div class="mb-5">

                    <Link
                        href="/countries"
                        class="inline-flex items-center gap-2 text-[10px] uppercase tracking-[0.18em] text-white/25 transition hover:text-white/60"
                    >
                        <span class="text-sm">←</span>
                        Countries
                    </Link>

                </div>


                <!-- HEADER -->

                <section class="mb-10">

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
                                Country
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                            >

                                <div class="flex items-center gap-3 sm:gap-5">

                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/[0.07] bg-white/[0.04] text-3xl shadow-2xl sm:h-20 sm:w-20 sm:text-5xl"
                                    >
                                        {{ flagEmoji(country.iso_code) }}
                                    </div>

                                    <div>

                                        <h1
                                            class="text-3xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl"
                                        >
                                            {{ country.name }}
                                        </h1>

                                        <p
                                            class="mt-3 max-w-xl text-xs leading-5 text-white/40 sm:mt-4 sm:text-base sm:leading-6"
                                        >
                                            A record of the places and trips you've experienced here.
                                        </p>

                                    </div>

                                </div>

                                <div
                                    class="grid grid-cols-2 gap-4 sm:flex sm:shrink-0 sm:gap-8"
                                >

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ cities.length }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            {{ cities.length === 1 ? 'City' : 'Cities' }}
                                        </div>

                                    </div>

                                    <div>

                                        <div
                                            class="text-2xl font-semibold tracking-[-0.05em] text-white sm:text-3xl"
                                        >
                                            {{ trips.length }}
                                        </div>

                                        <div
                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] text-white/25"
                                        >
                                            {{ trips.length === 1 ? 'Trip' : 'Trips' }}
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- CITIES -->

                <section class="mb-12">

                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">

                        <div>

                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Destinations
                            </div>

                            <h2
                                class="mt-2 text-2xl font-semibold tracking-[-0.045em] text-white"
                            >
                                Cities visited
                            </h2>

                        </div>

                        <div class="text-xs text-white/25">
                            {{ cities.length }}
                            {{ cities.length === 1 ? 'city' : 'cities' }}
                        </div>

                    </div>


                    <div
                        v-if="cities.length"
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >

                        <div
                            v-for="city in cities"
                            :key="city.id"
                            class="rounded-[20px] border border-white/[0.07] bg-white/[0.025] px-5 py-4 transition duration-300 hover:border-white/[0.12] hover:bg-white/[0.04]"
                        >

                            <div class="flex items-center justify-between gap-4">

                                <div class="min-w-0">

                                    <div
                                        class="truncate text-sm font-medium text-white"
                                    >
                                        {{ city.name }}
                                    </div>

                                    <div
                                        class="mt-1 text-[10px] uppercase tracking-[0.14em] text-white/25"
                                    >
                                        {{ city.visits_count }}
                                        {{ city.visits_count === 1 ? 'visit' : 'visits' }}
                                    </div>

                                </div>

                                <div
                                    class="h-2 w-2 shrink-0 rounded-full bg-cyan-300/60 shadow-[0_0_12px_rgba(103,232,249,0.35)]"
                                ></div>

                            </div>

                        </div>

                    </div>


                    <div
                        v-else
                        class="rounded-[24px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-16 text-center"
                    >

                        <p class="text-sm text-white/30">
                            No cities recorded yet.
                        </p>

                    </div>

                </section>


                <!-- TRIPS -->

                <section>

                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">

                        <div>

                            <div
                                class="text-[9px] uppercase tracking-[0.24em] text-white/25"
                            >
                                Travel history
                            </div>

                            <h2
                                class="mt-2 text-2xl font-semibold tracking-[-0.045em] text-white"
                            >
                                Trips in {{ country.name }}
                            </h2>

                        </div>

                        <div class="text-xs text-white/25">
                            {{ trips.length }}
                            {{ trips.length === 1 ? 'trip' : 'trips' }}
                        </div>

                    </div>


                    <div
                        v-if="trips.length"
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >

                        <Link
                            v-for="(trip, index) in trips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group overflow-hidden rounded-[24px] border border-white/[0.07] bg-white/[0.025] transition duration-300 hover:-translate-y-1 hover:border-white/[0.14] hover:bg-white/[0.04]"
                        >

                            <!-- COVER -->

                            <div
                                class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br"
                                :class="getTripGradient(index)"
                            >

                                <img
                                    v-if="trip.cover"
                                    :src="`/storage/${trip.cover}`"
                                    :alt="trip.name"
                                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                />

                                <div
                                    v-if="trip.cover"
                                    class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
                                ></div>

                                <div
                                    v-else
                                    class="absolute inset-0 flex items-center justify-center"
                                >

                                    <div
                                        class="text-[9px] uppercase tracking-[0.25em] text-white/20"
                                    >
                                        VyaMap
                                    </div>

                                </div>

                                <div
                                    class="absolute bottom-4 left-4 right-4"
                                >

                                    <div
                                        class="text-[9px] uppercase tracking-[0.18em] text-white/40"
                                    >
                                        {{ formatDate(trip.start_date) }}
                                        <span v-if="trip.end_date">
                                            — {{ formatDate(trip.end_date) }}
                                        </span>
                                    </div>

                                </div>

                            </div>


                            <!-- CONTENT -->

                            <div class="p-5">

                                <div
                                    class="flex items-start justify-between gap-4"
                                >

                                    <div class="min-w-0">

                                        <h3
                                            class="truncate text-lg font-medium tracking-[-0.025em] text-white"
                                        >
                                            {{ trip.name }}
                                        </h3>

                                        <p
                                            v-if="trip.description"
                                            class="mt-2 line-clamp-2 text-xs leading-5 text-white/30"
                                        >
                                            {{ trip.description }}
                                        </p>

                                    </div>

                                    <div
                                        class="shrink-0 text-lg text-white/15 transition duration-300 group-hover:translate-x-1 group-hover:text-white/60"
                                    >
                                        →
                                    </div>

                                </div>


                                <div
                                    v-if="trip.cities.length"
                                    class="mt-4 flex flex-wrap gap-1.5"
                                >

                                    <span
                                        v-for="city in trip.cities"
                                        :key="city"
                                        class="rounded-full border border-white/[0.06] bg-white/[0.025] px-2.5 py-1 text-[10px] text-white/35"
                                    >
                                        {{ city }}
                                    </span>

                                </div>

                            </div>

                        </Link>

                    </div>


                    <div
                        v-else
                        class="rounded-[24px] border border-dashed border-white/[0.10] bg-white/[0.02] px-6 py-16 text-center"
                    >

                        <p class="text-sm text-white/30">
                            No trips recorded in this country yet.
                        </p>

                    </div>

                </section>

            </main>

        </div>

    </AppLayout>

</template>