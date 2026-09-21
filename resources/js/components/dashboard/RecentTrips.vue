<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

defineProps<{ trips: Trip[] }>();

const formatDate = (date: string) =>
    new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
</script>

<template>
    <section>
        <div class="flex items-end justify-between gap-6">
            <div>
                <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                    History
                </p>

                <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                    Recent trips
                </h2>
            </div>

            <div class="flex shrink-0 items-center gap-4">
                <span class="text-sm text-[var(--vyamap-text-muted)]">
                    {{ trips.length }}
                    {{ trips.length === 1 ? 'trip' : 'trips' }}
                </span>

                <Link
                    href="/trips/create"
                    class="rounded-full bg-[var(--vyamap-text)] px-4 py-2 text-sm font-medium text-white transition-opacity hover:opacity-85"
                >
                    + New trip
                </Link>
            </div>
        </div>

        <div
            v-if="trips.length === 0"
            class="mt-5 border-y border-[var(--vyamap-border)] py-8"
        >
            <p class="text-sm text-[var(--vyamap-text-muted)]">
                No trips yet.
            </p>
        </div>

        <div v-else class="mt-5 border-t border-[var(--vyamap-border)]">
            <Link
                v-for="trip in trips"
                :key="trip.id"
                :href="`/trips/${trip.id}`"
                class="group flex items-center justify-between gap-6 border-b border-[var(--vyamap-border)] py-5 transition-colors hover:bg-[var(--vyamap-surface-secondary)]"
            >
                <div class="min-w-0">
                    <h3 class="truncate text-base font-semibold">
                        {{ trip.name }}
                    </h3>

                    <p
                        v-if="trip.description"
                        class="mt-1 truncate text-sm text-[var(--vyamap-text-muted)]"
                    >
                        {{ trip.description }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-6">
                    <span class="text-sm text-[var(--vyamap-text-muted)]">
                        {{ formatDate(trip.start_date) }} —
                        {{ formatDate(trip.end_date) }}
                    </span>

                    <span
                        class="text-lg text-[var(--vyamap-text-muted)] transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    >
                        →
                    </span>
                </div>
            </Link>
        </div>
    </section>
</template>