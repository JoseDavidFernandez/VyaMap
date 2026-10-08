<script setup lang="ts">
import AppLayout from '../../layouts/AppLayout.vue';
import TripOverviewMap from '../../components/trips/TripOverviewMap.vue';

import { computed } from 'vue';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
}

interface City {
    id: number;
    name: string;
    country: string;
    iso_code: string;
    latitude: number | null;
    longitude: number | null;
}

interface Visit {
    id: number;
    city: City;
    visited_from: string | null;
    visited_until: string | null;
    notes: string | null;
}

interface FlightAirport {
    id: number;
    name: string;
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
    origin: FlightAirport;
    destination: FlightAirport;
}

interface Photo {
    id: number;
    path: string;
    thumbnail_path: string | null;
    original_filename: string;
    width: number | null;
    height: number | null;
    taken_at: string | null;
}

interface GoogleMapList {
    id: number;
    name: string;
    url: string;
}

const props = defineProps<{
    trip: Trip;
    visits: Visit[];
    flights: Flight[];
    photos: Photo[];
    google_map_lists: GoogleMapList[];
}>();

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};

const formatShortDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
    }).format(new Date(date));
};

const formatTime = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(date));
};

const durationDays = computed(() => {
    if (!props.trip.start_date || !props.trip.end_date) {
        return null;
    }

    const start = new Date(
        props.trip.start_date,
    ).getTime();

    const end = new Date(
        props.trip.end_date,
    ).getTime();

    return Math.round(
        (end - start) / 86400000,
    ) + 1;
});

const countries = computed(() => {
    return Array.from(
        new Map(
            props.visits.map((visit) => [
                visit.city.iso_code,
                {
                    name: visit.city.country,
                    iso: visit.city.iso_code,
                },
            ]),
        ).values(),
    );
});

const totalPhotos = computed(
    () => props.photos.length,
);

const totalCities = computed(
    () => props.visits.length,
);

const firstPhoto = computed(() => {
    return props.photos[0] ?? null;
});

const remainingPhotos = computed(() => {
    return props.photos.slice(1, 5);
});

const tripFlights = computed(() => {
    return [...props.flights].sort((a, b) => {
        const aTime = a.departure
            ? new Date(a.departure).getTime()
            : 0;

        const bTime = b.departure
            ? new Date(b.departure).getTime()
            : 0;

        return aTime - bTime;
    });
});

const flagEmoji = (iso: string) => {
    return iso
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(
                letter.charCodeAt(0) + 127397,
            ),
        )
        .join('');
};
</script>

<template>
    <AppLayout :title="trip.name">

        <div class="min-h-screen bg-[#f7f7f5] text-[#1d2235]">

            <main class="mx-auto max-w-[1500px] px-5 pb-24 pt-6 sm:px-8 lg:px-10">

                <!-- =====================================================
                     HERO
                ====================================================== -->

                <section class="relative overflow-hidden rounded-[2px] border border-[#1d2235]/10 bg-[#1d2235] text-white">

                    <div
                        v-if="firstPhoto"
                        class="absolute inset-0 opacity-35"
                    >
                        <img
                            :src="`/storage/${firstPhoto.thumbnail_path || firstPhoto.path}`"
                            :alt="trip.name"
                            class="h-full w-full object-cover"
                        />

                        <div class="absolute inset-0 bg-[#1d2235]/70" />
                    </div>

                    <div class="relative px-7 py-12 sm:px-10 sm:py-16 lg:px-14 lg:py-20">

                        <div class="flex flex-col gap-12 lg:flex-row lg:items-end lg:justify-between">

                            <div class="max-w-4xl">

                                <div class="mb-5 flex items-center gap-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-white/40">
                                    <span>Trip {{ String(trip.id).padStart(2, '0') }}</span>

                                    <span class="h-px w-8 bg-white/20" />

                                    <span>VyaMap</span>
                                </div>

                                <h1 class="text-5xl font-semibold tracking-[-0.065em] sm:text-6xl lg:text-8xl">
                                    {{ trip.name }}
                                </h1>

                                <p
                                    v-if="trip.description"
                                    class="mt-6 max-w-2xl text-sm leading-6 text-white/55 sm:text-base"
                                >
                                    {{ trip.description }}
                                </p>

                            </div>


                            <div class="shrink-0">

                                <div class="text-[10px] font-semibold uppercase tracking-[0.18em] text-white/35">
                                    Dates
                                </div>

                                <div class="mt-2 text-lg font-medium">
                                    {{ formatDate(trip.start_date) }}
                                </div>

                                <div class="text-sm text-white/35">
                                    {{ formatDate(trip.end_date) }}
                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     SUMMARY
                ====================================================== -->

                <section class="grid grid-cols-2 border-b border-[#1d2235]/10 sm:grid-cols-4">

                    <div class="border-r border-[#1d2235]/10 px-5 py-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            {{ durationDays ?? '—' }}
                        </div>

                        <div class="mt-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Days
                        </div>
                    </div>

                    <div class="border-r border-[#1d2235]/10 px-5 py-7">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            {{ countries.length }}
                        </div>

                        <div class="mt-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Countries
                        </div>
                    </div>

                    <div class="border-r border-t border-[#1d2235]/10 px-5 py-7 sm:border-t-0">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            {{ totalCities }}
                        </div>

                        <div class="mt-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Cities
                        </div>
                    </div>

                    <div class="border-t border-[#1d2235]/10 px-5 py-7 sm:border-t-0">
                        <div class="text-3xl font-semibold tracking-[-0.05em]">
                            {{ totalPhotos }}
                        </div>

                        <div class="mt-1 text-[9px] font-semibold uppercase tracking-[0.16em] text-[#1d2235]/40">
                            Photos
                        </div>
                    </div>

                </section>


                <!-- =====================================================
                     MAP + DESTINATIONS
                ====================================================== -->

                <section class="mt-10 grid gap-5 lg:grid-cols-[1.55fr_0.65fr]">

                    <div class="overflow-hidden border border-[#1d2235]/10 bg-white">

                        <div class="flex items-end justify-between px-6 pt-6 sm:px-7">

                            <div>
                                <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/35">
                                    Geography
                                </div>

                                <h2 class="mt-2 text-2xl font-semibold tracking-[-0.04em]">
                                    Trip route
                                </h2>
                            </div>

                            <div class="text-right text-[10px] text-[#1d2235]/35">
                                {{ totalCities }}
                                cities
                            </div>

                        </div>

                        <div class="mt-5 h-[480px]">
                            <TripOverviewMap
                                :visits="visits"
                                :flights="flights"
                            />
                        </div>

                    </div>


                    <div class="border border-[#1d2235]/10 bg-white p-6 sm:p-7">

                        <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/35">
                            Destinations
                        </div>

                        <h2 class="mt-2 text-2xl font-semibold tracking-[-0.04em]">
                            Places visited
                        </h2>

                        <div class="mt-8">

                            <div
                                v-for="(visit, index) in visits"
                                :key="visit.id"
                                class="border-t border-[#1d2235]/10 py-5"
                            >

                                <div class="flex items-start gap-4">

                                    <div class="pt-1 text-[9px] font-semibold text-[#1d2235]/25">
                                        {{ String(index + 1).padStart(2, '0') }}
                                    </div>

                                    <div class="min-w-0 flex-1">

                                        <div class="flex items-center justify-between gap-3">
                                            <div class="text-lg font-semibold tracking-[-0.025em]">
                                                {{ visit.city.name }}
                                            </div>

                                            <div class="text-base">
                                                {{ flagEmoji(visit.city.iso_code) }}
                                            </div>
                                        </div>

                                        <div class="mt-1 text-[11px] text-[#1d2235]/40">
                                            {{ visit.city.country }}
                                        </div>

                                        <div class="mt-3 text-[10px] uppercase tracking-[0.12em] text-[#1d2235]/30">
                                            {{ formatShortDate(visit.visited_from) }}

                                            <span class="mx-1">
                                                —
                                            </span>

                                            {{ formatShortDate(visit.visited_until) }}
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div
                                v-if="!visits.length"
                                class="border-t border-[#1d2235]/10 py-8 text-sm text-[#1d2235]/35"
                            >
                                No cities recorded yet.
                            </div>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     PHOTOS
                ====================================================== -->

                <section
                    v-if="photos.length"
                    class="mt-16"
                >

                    <div class="mb-6 flex items-end justify-between">

                        <div>
                            <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/35">
                                Visual archive
                            </div>

                            <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                Photos
                            </h2>
                        </div>

                        <div class="text-[10px] text-[#1d2235]/35">
                            {{ photos.length }}
                            photos
                        </div>

                    </div>


                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">

                        <div
                            v-if="firstPhoto"
                            class="col-span-2 row-span-2 aspect-square overflow-hidden bg-[#e8e8e5]"
                        >
                            <img
                                :src="`/storage/${firstPhoto.thumbnail_path || firstPhoto.path}`"
                                :alt="trip.name"
                                class="h-full w-full object-cover transition duration-700 hover:scale-105"
                            />
                        </div>

                        <div
                            v-for="photo in remainingPhotos"
                            :key="photo.id"
                            class="aspect-square overflow-hidden bg-[#e8e8e5]"
                        >
                            <img
                                :src="`/storage/${photo.thumbnail_path || photo.path}`"
                                :alt="trip.name"
                                class="h-full w-full object-cover transition duration-700 hover:scale-105"
                            />
                        </div>

                    </div>

                </section>


                <!-- =====================================================
                     FLIGHTS
                ====================================================== -->

                <section class="mt-16">

                    <div class="mb-6 flex items-end justify-between">

                        <div>
                            <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/35">
                                Transport
                            </div>

                            <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                Flights
                            </h2>
                        </div>

                        <div class="text-[10px] text-[#1d2235]/35">
                            {{ flights.length }}
                            {{ flights.length === 1 ? 'flight' : 'flights' }}
                        </div>

                    </div>


                    <div
                        v-if="tripFlights.length"
                        class="overflow-hidden border-y border-[#1d2235]/10"
                    >

                        <article
                            v-for="(flight, index) in tripFlights"
                            :key="flight.id"
                            class="grid gap-5 border-b border-[#1d2235]/10 py-7 last:border-b-0 lg:grid-cols-[60px_1fr_0.7fr_0.7fr]"
                        >

                            <div class="hidden text-[10px] text-[#1d2235]/25 lg:block">
                                {{ String(index + 1).padStart(2, '0') }}
                            </div>

                            <div>

                                <div class="flex items-center gap-4">

                                    <div>
                                        <div class="text-xl font-semibold tracking-[-0.035em]">
                                            {{ flight.origin.city }}
                                        </div>

                                        <div class="mt-1 text-[10px] font-medium uppercase tracking-[0.12em] text-[#1d2235]/35">
                                            {{ flight.origin.name }}
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 text-[#1d2235]/30">
                                        <div class="h-px w-8 bg-[#1d2235]/15" />
                                        →
                                        <div class="h-px w-8 bg-[#1d2235]/15" />
                                    </div>

                                    <div>
                                        <div class="text-xl font-semibold tracking-[-0.035em]">
                                            {{ flight.destination.city }}
                                        </div>

                                        <div class="mt-1 text-[10px] font-medium uppercase tracking-[0.12em] text-[#1d2235]/35">
                                            {{ flight.destination.name }}
                                        </div>
                                    </div>

                                </div>

                            </div>


                            <div>
                                <div class="text-[9px] uppercase tracking-[0.16em] text-[#1d2235]/30">
                                    Flight
                                </div>

                                <div class="mt-2 text-sm font-semibold">
                                    {{ flight.flight_number }}
                                </div>

                                <div class="mt-1 text-[11px] text-[#1d2235]/40">
                                    {{ flight.airline || '—' }}
                                </div>
                            </div>


                            <div>
                                <div class="text-[9px] uppercase tracking-[0.16em] text-[#1d2235]/30">
                                    Departure
                                </div>

                                <div class="mt-2 text-sm font-medium">
                                    {{ formatShortDate(flight.departure) }}
                                </div>

                                <div class="mt-1 text-[11px] text-[#1d2235]/40">
                                    {{ formatTime(flight.departure) }}
                                </div>
                            </div>

                        </article>

                    </div>

                    <div
                        v-else
                        class="border-y border-[#1d2235]/10 py-10 text-sm text-[#1d2235]/35"
                    >
                        No flights recorded for this trip.
                    </div>

                </section>


                <!-- =====================================================
                     GOOGLE MAPS
                ====================================================== -->

                <section
                    v-if="google_map_lists.length"
                    class="mt-16 pb-10"
                >

                    <div class="mb-6">

                        <div class="text-[9px] font-semibold uppercase tracking-[0.18em] text-[#1d2235]/35">
                            Planning
                        </div>

                        <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                            Google Maps
                        </h2>

                    </div>


                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

                        <a
                            v-for="list in google_map_lists"
                            :key="list.id"
                            :href="list.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group border border-[#1d2235]/10 bg-white p-6 transition hover:-translate-y-0.5 hover:border-[#1d2235]/20"
                        >

                            <div class="flex items-center justify-between">

                                <span class="text-lg">
                                    ↗
                                </span>

                                <span class="text-[9px] uppercase tracking-[0.15em] text-[#1d2235]/30">
                                    Open
                                </span>

                            </div>

                            <div class="mt-10 text-lg font-semibold tracking-[-0.025em]">
                                {{ list.name }}
                            </div>

                            <div class="mt-2 text-[10px] text-[#1d2235]/35">
                                Google Maps list
                            </div>

                        </a>

                    </div>

                </section>

            </main>

        </div>

    </AppLayout>
</template>