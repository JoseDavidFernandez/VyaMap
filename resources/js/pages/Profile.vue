<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import ProfileTravelGlobe from '../components/ProfileTravelGlobe.vue';

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

const props = defineProps<{
    user: User;
    stats: Stats;
    countries: Country[];
    trips: ProfileTrip[];
}>();
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
    [...new Set(props.trips.map((trip) => trip.year).filter(Boolean))]
        .sort((a, b) => Number(b) - Number(a))
        .map(String),
);

const filteredTrips = computed(() =>
    activeFilter.value === 'All'
        ? props.trips
        : props.trips.filter((trip) => String(trip.year) === activeFilter.value),
);

const avatarUrl = computed(() => {
    const avatar = props.user.avatar;

    if (!avatar) {
        return null;
    }

    return avatar.startsWith('/') ||
        avatar.startsWith('http://') ||
        avatar.startsWith('https://')
        ? avatar
        : `/storage/${avatar}`;
});
</script>

<template>
    <AppLayout title="Profile">
        <div class="min-h-screen bg-[var(--vyamap-background)]">
            <main class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10">

                <!-- HERO -->
<!-- HERO -->
<section class="mb-7">
    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.08] bg-gradient-to-br from-cyan-300/[0.08] via-[#171b28] to-[#0d1119] px-5 py-7 sm:px-10 sm:py-11 lg:px-12 lg:py-12">
        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.05] blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.05] blur-3xl"></div>

        <div class="relative z-10">
            <div class="text-[9px] uppercase tracking-[0.24em] text-white/25">
                Traveller profile
            </div>

            <div class="mt-5 flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex min-w-0 flex-col gap-6 sm:flex-row sm:items-center">
                    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-[#0d1119] bg-white/[0.04] shadow-xl sm:h-36 sm:w-36">
                        <img
                            v-if="avatarUrl"
                            :src="avatarUrl"
                            :alt="props.user.name"
                            class="h-full w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-3xl font-semibold text-white"
                        >
                            {{ initials(props.user.name) }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h1 class="text-4xl font-semibold tracking-[-0.06em] text-white sm:text-6xl lg:text-7xl">
                            {{ props.user.name }}
                        </h1>

                        <div
                            v-if="props.countries.length"
                            class="mt-5 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="country in props.countries"
                                :key="country.iso_code"
                                :title="country.name"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-white/[0.08] bg-white/[0.03] text-lg"
                            >
                                {{ flagEmoji(country.iso_code) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="shrink-0">
                    <Link
                        href="/profile/edit"
                        class="inline-flex rounded-xl bg-white px-5 py-2.5 text-sm font-medium text-[#0d1119] transition hover:bg-white/90"
                    >
                        Edit profile
                    </Link>
                </div>
            </div>
        </div>
    </div>
</section>

                <!-- STATS -->
                <section class="mt-4 grid grid-cols-2 overflow-hidden rounded-[22px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] sm:grid-cols-4">
                    <div class="border-b border-[var(--vyamap-border)] p-6 sm:border-b-0 sm:border-r">
                        <p class="text-3xl font-semibold tracking-tight">{{ props.stats.countries }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Countries</p>
                    </div>
                    <div class="border-b border-[var(--vyamap-border)] p-6 sm:border-b-0 sm:border-r">
                        <p class="text-3xl font-semibold tracking-tight">{{ props.stats.cities }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Cities</p>
                    </div>
                    <div class="p-6 sm:border-r sm:border-[var(--vyamap-border)]">
                        <p class="text-3xl font-semibold tracking-tight">{{ props.stats.trips }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Trips</p>
                    </div>
                    <div class="p-6">
                        <p class="text-3xl font-semibold tracking-tight">{{ props.stats.flights }}</p>
                        <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">Flights</p>
                    </div>
                </section>

                <!-- TRAVEL FOOTPRINT -->
                <section
                    class="mt-4 overflow-hidden rounded-[28px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]"
                >
                    <div class="px-6 pt-7 sm:px-8">
                        <div class="flex items-end justify-between gap-6">
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-[var(--vyamap-text-muted)]">
                                    Your travel world
                                </p>

                                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.05em]">
                                    Travel footprint
                                </h2>

                                <p class="mt-2 text-sm text-[var(--vyamap-text-muted)]">
                                    The countries you have explored.
                                </p>
                            </div>

                            <div class="hidden text-right sm:block">
                                <div class="text-xl font-semibold">
                                    {{ props.countries.length }}
                                </div>

                                <div class="text-xs text-[var(--vyamap-text-muted)]">
                                    countries explored
                                </div>
                            </div>
                        </div>
                    </div>

                    <ProfileTravelGlobe
                        :countries="props.countries"
                    />
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
                            {{ props.stats.trips }} trips
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
