<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../layouts/AppLayout.vue';
import TripMap from '../../components/trips/TripMap.vue';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
}

interface Visit {
    id: number;
    city: {
        id: number;
        name: string;
        country: string;
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

const props = defineProps<{
    trip: Trip;
    visits: Visit[];
    flights: Flight[];
}>();

const formatDate = (date: string | null) => {
    if (!date) {
        return '';
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        console.warn('Invalid date:', date);
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsedDate);
};

const formatShortDate = (date: string | null) => {
    if (!date) {
        return '';
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
    }).format(parsedDate);
};

const formatDateTime = (date: string | null) => {
    if (!date) {
        return '';
    }

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(parsedDate);
};

const tripDuration = () => {
    if (!props.trip.start_date || !props.trip.end_date) {
        return null;
    }

    const start = new Date(`${props.trip.start_date}T00:00:00`);
    const end = new Date(`${props.trip.end_date}T00:00:00`);

    return Math.round((end.getTime() - start.getTime()) / 86400000) + 1;
};

const countryCount = new Set(
    props.visits.map((visit) => visit.city.iso_code),
).size;

const cityCount = new Set(
    props.visits.map((visit) => visit.city.id),
).size;

interface TimelineEvent {
    id: string;
    type: 'visit' | 'flight';
    date: string;
    visit?: Visit;
    flight?: Flight;
}

const timeline = computed<TimelineEvent[]>(() => {
    const events: TimelineEvent[] = [];

    props.visits.forEach((visit) => {
        const date = visit.visited_from ?? props.trip.start_date;

        if (!date) {
            return;
        }

        events.push({
            id: `visit-${visit.id}`,
            type: 'visit',
            date,
            visit,
        });
    });

    props.flights.forEach((flight) => {
        if (!flight.departure) {
            return;
        }

        events.push({
            id: `flight-${flight.id}`,
            type: 'flight',
            date: flight.departure,
            flight,
        });
    });

    return events.sort(
        (a, b) =>
            new Date(a.date).getTime() -
            new Date(b.date).getTime(),
    );
});

</script>

<template>
    <AppLayout :title="props.trip.name">
        <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
            <!-- Back -->
            <Link
                href="/"
                class="inline-flex items-center gap-2 text-sm font-medium text-[var(--vyamap-text-muted)] transition-colors hover:text-[var(--vyamap-text)]"
            >
                <span aria-hidden="true">←</span>
                My history
            </Link>

            <!-- Header -->
            <header class="mt-8">
                <div
                    class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-sm font-medium uppercase tracking-wider text-[var(--vyamap-text-muted)]"
                        >
                            Trip
                        </p>

                        <h1
                            class="mt-2 text-4xl font-semibold tracking-tight text-[var(--vyamap-text)] md:text-5xl"
                        >
                            {{ props.trip.name }}
                        </h1>

                        <p
                            v-if="props.trip.description"
                            class="mt-4 max-w-2xl text-base leading-7 text-[var(--vyamap-text-muted)]"
                        >
                            {{ props.trip.description }}
                        </p>
                    </div>

                    <div
                        v-if="props.trip.start_date && props.trip.end_date"
                        class="shrink-0 text-sm text-[var(--vyamap-text-muted)] md:text-right"
                    >
                        <p>
                            {{ formatDate(props.trip.start_date) }}
                            —
                            {{ formatDate(props.trip.end_date) }}
                        </p>

                        <p v-if="tripDuration()" class="mt-1">
                            {{ tripDuration() }}
                            {{ tripDuration() === 1 ? 'day' : 'days' }}
                        </p>
                    </div>
                </div>

                <!-- Summary -->
                <div
                    class="mt-8 flex flex-wrap gap-x-6 gap-y-2 border-y border-[var(--vyamap-border)] py-4 text-sm text-[var(--vyamap-text-muted)]"
                >
                    <span>
                        {{ cityCount }}
                        {{ cityCount === 1 ? 'city' : 'cities' }}
                    </span>

                    <span>
                        {{ countryCount }}
                        {{ countryCount === 1 ? 'country' : 'countries' }}
                    </span>

                    <span>
                        {{ props.flights.length }}
                        {{ props.flights.length === 1 ? 'flight' : 'flights' }}
                    </span>
                </div>
            </header>

            <!-- Map placeholder -->
            <section
                class="mt-10 overflow-hidden rounded-[var(--vyamap-radius-xl)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]"
            >
                <TripMap
                    :visits="props.visits"
                    :flights="props.flights"
                />
            </section>

            <!-- Timeline -->
            <section class="mt-14">
                <div
                    class="flex items-end justify-between border-b border-[var(--vyamap-border)] pb-4"
                >
                    <div>
                        <p
                            class="text-sm font-medium uppercase tracking-wider text-[var(--vyamap-text-muted)]"
                        >
                            Journey
                        </p>

                        <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                            Timeline
                        </h2>
                    </div>

                    <span class="text-sm text-[var(--vyamap-text-muted)]">
                        {{ timeline.length }}
                        {{ timeline.length === 1 ? 'event' : 'events' }}
                    </span>
                </div>

                <div
                    v-if="timeline.length === 0"
                    class="py-8"
                >
                    <p class="text-sm text-[var(--vyamap-text-muted)]">
                        No events recorded for this trip.
                    </p>
                </div>

                <div v-else class="relative">
                    <!-- Timeline line -->
                    <div
                        class="absolute bottom-0 left-[7px] top-0 w-px bg-[var(--vyamap-border)]"
                        aria-hidden="true"
                    />

                    <article
                        v-for="event in timeline"
                        :key="event.id"
                        class="relative flex gap-6 border-b border-[var(--vyamap-border)] py-6 pl-0"
                    >
                        <!-- Marker -->
                        <div class="relative z-10 mt-1 shrink-0">
                            <div
                                class="h-[15px] w-[15px] rounded-full border-2 border-[var(--vyamap-surface)] bg-[var(--vyamap-text)] ring-1 ring-[var(--vyamap-border)]"
                            />
                        </div>

                        <!-- Event -->
                        <div class="min-w-0 flex-1">
                            <!-- Visit -->
                            <template v-if="event.type === 'visit' && event.visit">
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-medium uppercase tracking-wider text-[var(--vyamap-text-muted)]"
                                        >
                                            Visit
                                        </p>

                                        <h3 class="mt-1 text-lg font-semibold">
                                            {{ event.visit.city.name }}
                                        </h3>

                                        <p
                                            class="mt-1 text-sm text-[var(--vyamap-text-muted)]"
                                        >
                                            {{ event.visit.city.country }}
                                        </p>
                                    </div>

                                    <div
                                        class="shrink-0 text-sm text-[var(--vyamap-text-muted)] sm:text-right"
                                    >
                                        <p>
                                            {{ formatShortDate(event.visit.visited_from) }}
                                            <span
                                                v-if="event.visit.visited_until"
                                            >
                                                —
                                                {{
                                                    formatShortDate(
                                                        event.visit.visited_until,
                                                    )
                                                }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <!-- Flight -->
                            <template v-if="event.type === 'flight' && event.flight">
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-medium uppercase tracking-wider text-[var(--vyamap-text-muted)]"
                                        >
                                            Flight
                                        </p>

                                        <h3 class="mt-1 text-lg font-semibold">
                                            {{ event.flight.origin.city }}
                                            <span
                                                class="mx-2 text-[var(--vyamap-text-muted)]"
                                            >
                                                →
                                            </span>
                                            {{ event.flight.destination.city }}
                                        </h3>

                                        <p
                                            class="mt-1 text-sm text-[var(--vyamap-text-muted)]"
                                        >
                                            {{ event.flight.flight_number }}

                                            <span v-if="event.flight.airline">
                                                · {{ event.flight.airline }}
                                            </span>
                                        </p>
                                    </div>

                                    <div
                                        class="shrink-0 text-sm text-[var(--vyamap-text-muted)] sm:text-right"
                                    >
                                        {{ formatDateTime(event.flight.departure) }}
                                    </div>
                                </div>
                            </template>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>