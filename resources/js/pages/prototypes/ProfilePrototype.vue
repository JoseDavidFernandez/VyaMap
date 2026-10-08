<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

interface User {
    name: string;
    email: string;
    avatar: string | null;
}

interface Stats {
    countries: number;
    cities: number;
    trips: number;
    flights: number;
}

interface Country {
    name: string;
    iso_code: string;
}

interface ProfileTrip {
    id: number;
    name: string;
    location: string;
    year: number | null;
    cover: string | null;
}

const user: User = {
    name: 'José David',
    email: 'jose@example.com',
    avatar: null,
};

const stats: Stats = {
    countries: 12,
    cities: 31,
    trips: 8,
    flights: 18,
};

const countries: Country[] = [
    { name: 'Spain', iso_code: 'ES' },
    { name: 'Portugal', iso_code: 'PT' },
    { name: 'Italy', iso_code: 'IT' },
    { name: 'Croatia', iso_code: 'HR' },
    { name: 'Greece', iso_code: 'GR' },
    { name: 'Albania', iso_code: 'AL' },
];

const trips: ProfileTrip[] = [
    { id: 1, name: 'Albania', location: 'Tirana · Sarandë · Ksamil', year: 2026, cover: null },
    { id: 2, name: 'Croatia', location: 'Dubrovnik · Split · Hvar', year: 2025, cover: null },
    { id: 3, name: 'Italy', location: 'Rome · Florence · Venice', year: 2025, cover: null },
    { id: 4, name: 'Portugal', location: 'Lisbon · Porto', year: 2024, cover: null },
    { id: 5, name: 'Switzerland', location: 'Zürich · Interlaken', year: 2024, cover: null },
    { id: 6, name: 'Denmark', location: 'Copenhagen', year: 2023, cover: null },
];

const flagEmoji = (isoCode: string) =>
    isoCode
        .toUpperCase()
        .split('')
        .map((letter) => String.fromCodePoint(letter.charCodeAt(0) + 127397))
        .join('');

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();

const activeFilter = ref('All');

const years = computed(() =>
    [...new Set(trips.map((trip) => trip.year).filter(Boolean))]
        .sort((a, b) => Number(b) - Number(a))
        .map(String),
);

const filteredTrips = computed(() =>
    activeFilter.value === 'All'
        ? trips
        : trips.filter((trip) => String(trip.year) === activeFilter.value),
);

const avatarUrl = computed(() => {
    if (!user.avatar) return null;
    return user.avatar.startsWith('/') || user.avatar.startsWith('http')
        ? user.avatar
        : `/storage/${user.avatar}`;
});
</script>

<template>
    <AppLayout title="Profile">
        <div class="min-h-screen bg-[var(--vyamap-background)]">
            <main class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10">

                <!-- HERO -->
                <section class="relative overflow-hidden rounded-[28px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_78%_15%,rgba(34,211,238,0.10),transparent_32%),radial-gradient(circle_at_18%_100%,rgba(139,92,246,0.08),transparent_35%)]"></div>

                    <div class="relative grid gap-10 p-7 sm:p-10 lg:grid-cols-[1fr_auto] lg:items-center lg:p-12">
                        <div class="flex flex-col gap-7 sm:flex-row sm:items-center">
                            <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-[var(--vyamap-background)] bg-[var(--vyamap-surface-strong)] shadow-xl sm:h-36 sm:w-36">
                                <img
                                    v-if="avatarUrl"
                                    :src="avatarUrl"
                                    :alt="user.name"
                                    class="h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-3xl font-semibold">
                                    {{ initials(user.name) }}
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--vyamap-text-muted)]">
                                    Traveller profile
                                </p>
                                <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">
                                    {{ user.name }}
                                </h1>

                                <div v-if="countries.length" class="mt-5 flex flex-wrap gap-2">
                                    <span
                                        v-for="country in countries"
                                        :key="country.iso_code"
                                        :title="country.name"
                                        class="flex h-9 w-9 items-center justify-center rounded-full border border-[var(--vyamap-border)] bg-[var(--vyamap-background)] text-lg"
                                    >
                                        {{ flagEmoji(country.iso_code) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 lg:self-end">
                            <Link
                                href="/profile/edit"
                                class="inline-flex rounded-xl bg-[var(--vyamap-text)] px-5 py-2.5 text-sm font-medium text-[var(--vyamap-background)] transition hover:opacity-90"
                            >
                                Edit profile
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- STATS -->
                <section class="mt-4 grid grid-cols-2 overflow-hidden rounded-[22px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] sm:grid-cols-4">
                    <div class="border-b border-[var(--vyamap-border)] p-6 sm:border-b-0 sm:border-r">
                        <p class="text-3xl font-semibold tracking-tight">{{ stats.countries }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Countries</p>
                    </div>
                    <div class="border-b border-[var(--vyamap-border)] p-6 sm:border-b-0 sm:border-r">
                        <p class="text-3xl font-semibold tracking-tight">{{ stats.cities }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Cities</p>
                    </div>
                    <div class="p-6 sm:border-r sm:border-[var(--vyamap-border)]">
                        <p class="text-3xl font-semibold tracking-tight">{{ stats.trips }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Trips</p>
                    </div>
                    <div class="p-6">
                        <p class="text-3xl font-semibold tracking-tight">{{ stats.flights }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Flights</p>
                    </div>
                </section>

                <!-- TRAVEL FOOTPRINT -->
                <section class="mt-16">
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--vyamap-text-muted)]">
                            Your travel world
                        </p>
                        <h2 class="mt-2 text-3xl font-semibold tracking-tight">Travel footprint</h2>
                    </div>

                    <div class="overflow-hidden rounded-[24px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
                        <div class="relative flex min-h-[260px] items-center justify-center overflow-hidden bg-[#10151b] p-8">
                            <div class="absolute inset-0 opacity-50 [background-image:radial-gradient(circle_at_50%_50%,rgba(255,255,255,0.08)_1px,transparent_1px)] [background-size:18px_18px]"></div>
                            <div class="relative max-w-xl text-center">
                                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full border border-white/10 bg-white/5 text-2xl">
                                    🌍
                                </div>
                                <p class="text-sm font-medium text-white">Your world is taking shape</p>
                                <p class="mt-2 text-sm leading-6 text-white/50">
                                    {{ stats.countries }} countries · {{ stats.cities }} cities · {{ stats.flights }} flights
                                </p>
                                <p class="mt-4 text-xs text-white/35">
                                    Interactive world map can live here when we connect the profile footprint to the existing map data.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- TRIPS -->
                <section class="mt-16">
                    <div class="mb-7 flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--vyamap-text-muted)]">
                                Travel history
                            </p>
                            <h2 class="mt-2 text-3xl font-semibold tracking-tight">Trips</h2>
                        </div>

                        <span class="text-sm text-[var(--vyamap-text-subtle)]">
                            {{ stats.trips }} trips
                        </span>
                    </div>

                    <div v-if="years.length" class="mb-6 flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="filter in ['All', ...years]"
                            :key="filter"
                            type="button"
                            class="shrink-0 rounded-full border px-4 py-2 text-sm transition"
                            :class="activeFilter === filter
                                ? 'border-[var(--vyamap-text)] bg-[var(--vyamap-text)] text-[var(--vyamap-background)]'
                                : 'border-[var(--vyamap-border)] text-[var(--vyamap-text-muted)] hover:text-[var(--vyamap-text)]'"
                            @click="activeFilter = filter"
                        >
                            {{ filter }}
                        </button>
                    </div>

                    <div v-if="filteredTrips.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="trip in filteredTrips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group relative aspect-[1.15/1] overflow-hidden rounded-[22px] bg-[var(--vyamap-surface)]"
                        >
                            <img
                                :src="trip.cover ? `/storage/${trip.cover}` : '/images/trip-placeholder.jpg'"
                                :alt="trip.name"
                                class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/10 to-transparent"></div>

                            <div class="absolute inset-x-0 bottom-0 p-5">
                                <div class="flex items-end justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-lg font-medium text-white">{{ trip.name }}</p>
                                        <p class="mt-1 truncate text-sm text-white/60">
                                            {{ trip.location }}
                                            <span v-if="trip.year"> · {{ trip.year }}</span>
                                        </p>
                                    </div>
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/20 bg-black/20 text-white backdrop-blur transition group-hover:bg-white group-hover:text-black">
                                        →
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div
                        v-else
                        class="flex min-h-64 items-center justify-center rounded-[24px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-8 text-center"
                    >
                        <div>
                            <p class="font-medium">No trips yet</p>
                            <p class="mt-2 text-sm text-[var(--vyamap-text-muted)]">
                                Your trips will appear here.
                            </p>
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </AppLayout>
</template>
