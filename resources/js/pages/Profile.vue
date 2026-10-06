<script setup lang="ts">
import AppLayout from '../layouts/AppLayout.vue';
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

const props = defineProps<{
    user: User;
    stats: Stats;
    countries: Country[];
    trips: ProfileTrip[];
}>();

const flagEmoji = (isoCode: string) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(
                letter.charCodeAt(0) + 127397,
            ),
        )
        .join('');
};


const initials = (name: string) => {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
};
</script>

<template>
    <AppLayout title="Profile">
        <div class="vyamap-page">
            <main
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >

                <!-- ===================================================== -->
                <!-- PROFILE HEADER                                         -->
                <!-- ===================================================== -->

                <section
                    class="border-b border-[var(--vyamap-border)] pb-10"
                >
                    <div
                        class="flex flex-col gap-7 sm:flex-row sm:items-start"
                    >

                        <!-- Avatar -->

                        <div
                            class="h-28 w-28 shrink-0 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)] sm:h-32 sm:w-32"
                        >
                            <img
                                v-if="props.user.avatar"
                                :src="`/storage/${props.user.avatar}`"
                                :alt="props.user.name"
                                class="h-full w-full object-cover"
                            />

                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-2xl font-semibold"
                            >
                                {{ initials(props.user.name) }}
                            </div>
                        </div>


                        <!-- Profile information -->

                        <div class="min-w-0 flex-1">

                            <h1
                                class="text-2xl font-semibold tracking-tight sm:text-3xl"
                            >
                                {{ props.user.name }}
                            </h1>


                            <!-- Countries -->

                            <div
                                v-if="props.countries.length"
                                class="mt-4 flex flex-wrap items-center gap-2"
                            >
                                <span
                                    v-for="country in props.countries"
                                    :key="country.iso_code"
                                    :title="country.name"
                                    class="text-xl leading-none"
                                >
                                    {{ flagEmoji(country.iso_code) }}
                                </span>
                            </div>


                            <!-- Stats -->

                            <div
                                class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3"
                            >
                                <div>
                                    <span class="font-semibold">
                                        {{ props.stats.trips }}
                                    </span>

                                    <span
                                        class="ml-1 text-sm text-[var(--vyamap-text-muted)]"
                                    >
                                        Trips
                                    </span>
                                </div>

                                <div>
                                    <span class="font-semibold">
                                        {{ props.stats.countries }}
                                    </span>

                                    <span
                                        class="ml-1 text-sm text-[var(--vyamap-text-muted)]"
                                    >
                                        Countries
                                    </span>
                                </div>

                                <div>
                                    <span class="font-semibold">
                                        {{ props.stats.cities }}
                                    </span>

                                    <span
                                        class="ml-1 text-sm text-[var(--vyamap-text-muted)]"
                                    >
                                        Cities
                                    </span>
                                </div>
                            </div>


                            <!-- Edit -->

                            <div class="mt-6">
                                <Link
                                    href="/profile/edit"
                                    class="inline-flex rounded-xl bg-[var(--vyamap-text)] px-5 py-2.5 text-sm font-medium text-[var(--vyamap-background)] transition hover:opacity-90"
                                >
                                    Edit profile
                                </Link>
                            </div>

                        </div>
                    </div>
                </section>


                <!-- ===================================================== -->
                <!-- TRIPS                                                    -->
                <!-- ===================================================== -->

                <section class="mt-10">

                    <div
                        class="mb-5 flex items-center justify-between gap-4"
                    >
                        <div>
                            <p
                                class="text-sm font-medium text-[var(--vyamap-text-muted)]"
                            >
                                Travel history
                            </p>

                            <h2
                                class="mt-1 text-2xl font-semibold tracking-tight"
                            >
                                Trips
                            </h2>
                        </div>

                        <span
                            class="text-sm text-[var(--vyamap-text-subtle)]"
                        >
                            {{ props.stats.trips }}
                        </span>
                    </div>


                    <!-- Empty state -->

                    <div
                        v-if="props.trips.length === 0"
                        class="vyamap-card-lg flex min-h-64 items-center justify-center p-8 text-center"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                No trips yet
                            </p>

                            <p
                                class="mt-2 text-sm text-[var(--vyamap-text-muted)]"
                            >
                                Your trips will appear here.
                            </p>
                        </div>
                    </div>


                    <!-- Trip grid -->
                    <div
                        v-else
                        class="grid grid-cols-2 gap-2 md:grid-cols-4 lg:grid-cols-5 lg:gap-3"
                    >
                        <Link
                            v-for="trip in props.trips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group relative aspect-square overflow-hidden rounded-[var(--vyamap-radius-md)] bg-[var(--vyamap-surface)]"
                        >
                            <img
                                :src="trip.cover ? `/storage/${trip.cover}` : '/images/trip-placeholder.jpg'"
                                :alt="trip.name"
                                class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                            />

                            <div
                                class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent p-3 pt-10"
                            >
                                <div class="truncate text-sm font-medium text-white">
                                    {{ trip.name }}
                                </div>

                                <div class="mt-0.5 truncate text-[10px] text-white/60">
                                    {{ trip.location }}
                                    <span v-if="trip.year"> · {{ trip.year }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                </section>

            </main>
        </div>
    </AppLayout>
</template>