<script setup lang="ts">

// Imports

import { computed, ref } from 'vue';

import { Link, useForm } from '@inertiajs/vue3';

import AppLayout from '../../layouts/AppLayout.vue';

import TripMap from '../../components/trips/TripMap.vue';


// Types

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

interface CitySearchResult {
    source: 'local' | 'geoapify';
    id: number | null;
    name: string;
    country: string;
    iso_code: string;
    latitude: number;
    longitude: number;
    external_id?: string;
}

interface TimelineEvent {
    id: string;
    type: 'visit' | 'flight';
    date: string;
    visit?: Visit;
    flight?: Flight;
}


// Props
const props = defineProps<{
    trip: Trip;
    visits: Visit[];
    flights: Flight[];
}>();


// State

const showAddCityForm = ref(false);

const citySearchQuery = ref('');
const citySearchResults = ref<CitySearchResult[]>([]);
const citySearchLoading = ref(false);
const citySaving = ref(false);

const selectedCity = ref<CitySearchResult | null>(null);

const editingVisitId = ref<number | null>(null);

const editVisitForm = useForm({
    city_id: '',
    visited_from: '',
    visited_until: '',
    notes: '',
});

// Forms

const visitForm = useForm({
    city_id: '',
    visited_from: props.trip.start_date ?? '',
    visited_until: props.trip.end_date ?? '',
    notes: '',
});


// Computed / Derived data

const countryCount = new Set(
    props.visits.map((visit) => visit.city.iso_code),
).size;

const cityCount = new Set(
    props.visits.map((visit) => visit.city.id),
).size;

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


// City search

let citySearchTimeout: ReturnType<typeof setTimeout> | null = null;

const searchCities = () => {
    if (citySearchTimeout) {
        clearTimeout(citySearchTimeout);
    }

    const query = citySearchQuery.value.trim();

    if (query.length < 2) {
        citySearchResults.value = [];
        citySearchLoading.value = false;
        return;
    }

    citySearchTimeout = setTimeout(async () => {
        citySearchLoading.value = true;

        try {
            const response = await fetch(
                `/cities/search?q=${encodeURIComponent(query)}`,
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Unable to search cities.');
            }

            const data = await response.json();

            citySearchResults.value = data.results;
        } catch (error) {
            console.error(error);
            citySearchResults.value = [];
        } finally {
            citySearchLoading.value = false;
        }
    }, 300);
};


// Actions

const selectCity = async (city: CitySearchResult) => {
    citySearchResults.value = [];

    if (city.source === 'local' && city.id !== null) {
        selectedCity.value = city;
        citySearchQuery.value = city.name;
        visitForm.city_id = String(city.id);

        return;
    }

    if (city.source !== 'geoapify') {
        return;
    }

    citySaving.value = true;

    try {
        const response = await fetch('/cities', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                name: city.name,
                country: city.country,
                iso_code: city.iso_code,
                latitude: city.latitude,
                longitude: city.longitude,
                external_provider: city.source,
                external_id: city.external_id,
            }),
        });

        if (!response.ok) {
            throw new Error('Unable to save city.');
        }

        const data = await response.json();

        selectedCity.value = {
            ...city,
            id: data.city.id,
        };

        citySearchQuery.value = city.name;
        visitForm.city_id = String(data.city.id);
    } catch (error) {
        console.error(error);
    } finally {
        citySaving.value = false;
    }
};

const clearSelectedCity = () => {
    selectedCity.value = null;
    citySearchQuery.value = '';
    citySearchResults.value = [];
    visitForm.city_id = '';
};

const resetCitySearch = () => {
    citySearchQuery.value = '';
    citySearchResults.value = [];
    citySearchLoading.value = false;
    selectedCity.value = null;
    visitForm.reset('city_id');
};

const submitVisit = () => {
    visitForm.post(`/trips/${props.trip.id}/visits`, {
        preserveScroll: true,
        onSuccess: () => {
            visitForm.reset('city_id', 'notes');
            selectedCity.value = null;
            citySearchQuery.value = '';
            citySearchResults.value = [];
            showAddCityForm.value = false;
        },
    });
};

const startEditingVisit = (visit: Visit) => {
    editingVisitId.value = visit.id;

    editVisitForm.city_id = String(visit.city.id);
    editVisitForm.visited_from = visit.visited_from ?? '';
    editVisitForm.visited_until = visit.visited_until ?? '';
    editVisitForm.notes = visit.notes ?? '';

    editVisitForm.clearErrors();
};

const cancelEditingVisit = () => {
    editingVisitId.value = null;
    editVisitForm.reset();
    editVisitForm.clearErrors();
};

const submitEditVisit = () => {
    if (editingVisitId.value === null) {
        return;
    }

    editVisitForm.put(
        `/trips/${props.trip.id}/visits/${editingVisitId.value}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editingVisitId.value = null;
                editVisitForm.reset();
            },
        },
    );
};

const toggleAddCityForm = () => {
    if (showAddCityForm.value) {
        showAddCityForm.value = false;
        resetCitySearch();
        return;
    }

    showAddCityForm.value = true;
};


// Formatters

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

const formatVisitDateRange = (
    from: string | null,
    until: string | null,
) => {
    if (!from && !until) {
        return '';
    }

    if (from && until && from === until) {
        return formatShortDate(from);
    }

    if (from && until) {
        return `${formatShortDate(from)} — ${formatShortDate(until)}`;
    }

    return formatShortDate(from ?? until);
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

const tripDuration = computed(() => {
    if (!props.trip.start_date || !props.trip.end_date) {
        return null;
    }

    const start = new Date(`${props.trip.start_date}T00:00:00`);
    const end = new Date(`${props.trip.end_date}T00:00:00`);

    return (
        Math.round(
            (end.getTime() - start.getTime()) / 86400000,
        ) + 1
    );
});

</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">

            <!-- ========================================================= -->
            <!-- Trip header                                               -->
            <!-- ========================================================= -->

            <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                <div>
                    <Link
                        href="/"
                        class="text-sm text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                    >
                        ← Back to dashboard
                    </Link>

                    <div class="mt-4">
                        <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                            Trip
                        </p>

                        <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                            {{ props.trip.name }}
                        </h1>

                        <p
                            v-if="props.trip.description"
                            class="mt-3 max-w-2xl text-sm leading-6 text-[var(--vyamap-text-muted)]"
                        >
                            {{ props.trip.description }}
                        </p>
                    </div>
                </div>

                <div class="text-sm text-[var(--vyamap-text-muted)] md:text-right">
                    <p>
                        {{ formatDate(props.trip.start_date) }}
                        <span v-if="props.trip.end_date">
                            — {{ formatDate(props.trip.end_date) }}
                        </span>
                    </p>

                    <p
                        v-if="tripDuration"
                        class="mt-1"
                    >
                        {{ tripDuration }}
                    </p>
                </div>
            </div>


            <!-- ========================================================= -->
            <!-- Trip summary                                               -->
            <!-- ========================================================= -->

            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                <div
                    class="rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-5"
                >
                    <p class="text-sm text-[var(--vyamap-text-muted)]">
                        Cities
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ cityCount }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-5"
                >
                    <p class="text-sm text-[var(--vyamap-text-muted)]">
                        Countries
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ countryCount }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-5"
                >
                    <p class="text-sm text-[var(--vyamap-text-muted)]">
                        Flights
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ props.flights.length }}
                    </p>
                </div>
            </div>


            <!-- ========================================================= -->
            <!-- Map                                                        -->
            <!-- ========================================================= -->

            <section class="mt-8">
                <div>
                    <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                        Route
                    </p>

                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        Trip map
                    </h2>
                </div>

                <div
                    class="mt-4 overflow-hidden rounded-2xl border border-[var(--vyamap-border)]"
                >
                    <TripMap
                        :visits="props.visits"
                        :flights="props.flights"
                    />
                </div>
            </section>


            <!-- ========================================================= -->
            <!-- Timeline                                                    -->
            <!-- ========================================================= -->

            <section class="mt-8">
                <div>
                    <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                        Journey
                    </p>

                    <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                        Timeline
                    </h2>
                </div>

                <div
                    v-if="timeline.length > 0"
                    class="mt-4 overflow-hidden rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]"
                >
                    <div
                        v-for="(event, index) in timeline"
                        :key="event.id"
                        class="relative px-5 py-5"
                        :class="{
                            'border-b border-[var(--vyamap-border)]':
                                index < timeline.length - 1,
                        }"
                    >
                        <!-- Visit -->
                        <template v-if="event.type === 'visit' && event.visit">
                            <div class="flex items-start gap-4">
                                <div
                                    class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-[var(--vyamap-text)]"
                                />

                                <div class="min-w-0">
                                    <p class="text-sm font-medium">
                                        {{ event.visit.city.name }}
                                    </p>

                                    <p class="mt-1 text-xs text-[var(--vyamap-text-muted)]">
                                        {{ event.visit.city.country }}
                                    </p>

                                    <p class="mt-2 text-xs text-[var(--vyamap-text-muted)]">
                                        {{
                                            formatVisitDateRange(
                                                event.visit.visited_from,
                                                event.visit.visited_until,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-if="event.visit.notes"
                                        class="mt-2 text-sm leading-6 text-[var(--vyamap-text-muted)]"
                                    >
                                        {{ event.visit.notes }}
                                    </p>
                                </div>
                            </div>
                        </template>

                        <!-- Flight -->
                        <template v-else-if="event.type === 'flight' && event.flight">
                            <div class="flex items-start gap-4">
                                <div
                                    class="mt-1 flex h-2.5 w-2.5 shrink-0 items-center justify-center"
                                >
                                    <span class="h-2 w-2 rounded-full border border-[var(--vyamap-text-muted)]" />
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-medium">
                                            {{ event.flight.origin.city }}
                                            →
                                            {{ event.flight.destination.city }}
                                        </p>

                                        <span
                                            v-if="event.flight.flight_number"
                                            class="rounded-full bg-[var(--vyamap-surface-muted)] px-2 py-0.5 text-[10px] uppercase tracking-wide text-[var(--vyamap-text-muted)]"
                                        >
                                            {{ event.flight.flight_number }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs text-[var(--vyamap-text-muted)]">
                                        {{ event.flight.origin.name }}
                                        →
                                        {{ event.flight.destination.name }}
                                    </p>

                                    <p
                                        v-if="event.flight.departure"
                                        class="mt-2 text-xs text-[var(--vyamap-text-muted)]"
                                    >
                                        {{ formatDateTime(event.flight.departure) }}
                                    </p>

                                    <p
                                        v-if="event.flight.airline"
                                        class="mt-1 text-xs text-[var(--vyamap-text-muted)]"
                                    >
                                        {{ event.flight.airline }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-2xl border border-dashed border-[var(--vyamap-border)] px-6 py-10 text-center"
                >
                    <p class="text-sm text-[var(--vyamap-text-muted)]">
                        No events have been added to this trip yet.
                    </p>
                </div>
            </section>


            <!-- ========================================================= -->
            <!-- Visited cities                                             -->
            <!-- ========================================================= -->

            <section class="mt-8">

                <!-- Section header -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                            Cities
                        </p>

                        <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                            Visited cities
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-[var(--vyamap-border)] px-4 py-2 text-sm font-medium transition hover:bg-[var(--vyamap-surface-muted)]"
                        @click="toggleAddCityForm"
                    >
                        {{ showAddCityForm ? 'Cancel' : '+ Add city' }}
                    </button>
                </div>


                <!-- Add city panel -->
                <div
                    v-if="showAddCityForm"
                    class="mt-4 rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-6"
                >
                    <form
                        class="space-y-6"
                        @submit.prevent="submitVisit"
                    >

                        <!-- City search -->
                        <div>
                            <label
                                for="visit-city"
                                class="block text-sm font-medium"
                            >
                                City
                            </label>

                            <div class="relative mt-2">
                                <input
                                    id="visit-city"
                                    v-model="citySearchQuery"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Search a city..."
                                    class="w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                    @input="searchCities"
                                />

                                <!-- Loading -->
                                <div
                                    v-if="citySearchLoading || citySaving"
                                    class="absolute inset-y-0 right-4 flex items-center"
                                >
                                    <span class="text-xs text-[var(--vyamap-text-muted)]">
                                        {{ citySaving ? 'Saving city...' : 'Searching...' }}
                                    </span>
                                </div>

                                <!-- Search results -->
                                <div
                                    v-if="citySearchResults.length > 0"
                                    class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] shadow-lg"
                                >
                                    <button
                                        v-for="city in citySearchResults"
                                        :key="`${city.source}-${city.external_id ?? city.id}-${city.name}`"
                                        type="button"
                                        :disabled="citySaving"
                                        class="flex w-full items-center justify-between px-4 py-3 text-left transition hover:bg-[var(--vyamap-surface-muted)]"
                                        @click="selectCity(city)"
                                    >
                                        <div>
                                            <p class="text-sm font-medium">
                                                {{ city.name }}
                                            </p>

                                            <p class="mt-0.5 text-xs text-[var(--vyamap-text-muted)]">
                                                {{ city.country }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="city.source === 'geoapify'"
                                            class="text-[10px] uppercase tracking-wide text-[var(--vyamap-text-muted)]"
                                        >
                                            External
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <!-- Selected city -->
                            <div
                                v-if="selectedCity"
                                class="mt-2 flex items-center justify-between rounded-xl border border-[var(--vyamap-border)] px-4 py-3"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ selectedCity.name }}
                                    </p>

                                    <p class="mt-0.5 text-xs text-[var(--vyamap-text-muted)]">
                                        {{ selectedCity.country }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="text-xs text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                                    @click="clearSelectedCity"
                                >
                                    Change
                                </button>
                            </div>

                            <p
                                v-if="visitForm.errors.city_id"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ visitForm.errors.city_id }}
                            </p>
                        </div>


                        <!-- Dates -->
                        <div class="grid gap-6 md:grid-cols-2">

                            <div>
                                <label
                                    for="visited-from"
                                    class="block text-sm font-medium"
                                >
                                    From
                                </label>

                                <input
                                    id="visited-from"
                                    v-model="visitForm.visited_from"
                                    type="date"
                                    class="mt-2 w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                />

                                <p
                                    v-if="visitForm.errors.visited_from"
                                    class="mt-2 text-sm text-red-500"
                                >
                                    {{ visitForm.errors.visited_from }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="visited-until"
                                    class="block text-sm font-medium"
                                >
                                    Until
                                </label>

                                <input
                                    id="visited-until"
                                    v-model="visitForm.visited_until"
                                    type="date"
                                    class="mt-2 w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                />

                                <p
                                    v-if="visitForm.errors.visited_until"
                                    class="mt-2 text-sm text-red-500"
                                >
                                    {{ visitForm.errors.visited_until }}
                                </p>
                            </div>
                        </div>


                        <!-- Notes -->
                        <div>
                            <label
                                for="visit-notes"
                                class="block text-sm font-medium"
                            >
                                Notes
                            </label>

                            <textarea
                                id="visit-notes"
                                v-model="visitForm.notes"
                                rows="3"
                                placeholder="Optional notes about this visit..."
                                class="mt-2 w-full resize-none rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                            />

                            <p
                                v-if="visitForm.errors.notes"
                                class="mt-2 text-sm text-red-500"
                            >
                                {{ visitForm.errors.notes }}
                            </p>
                        </div>


                        <!-- Form actions -->
                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-sm font-medium text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                                @click="showAddCityForm = false"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="visitForm.processing"
                                class="rounded-xl bg-[var(--vyamap-text)] px-4 py-2 text-sm font-medium text-[var(--vyamap-background)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ visitForm.processing ? 'Adding...' : 'Add city' }}
                            </button>
                        </div>

                    </form>
                </div>


                <!-- Visited cities list -->
                <div
                    v-if="props.visits.length > 0"
                    class="mt-4 grid gap-4 md:grid-cols-2"
                >
                    <div
                        v-for="visit in props.visits"
                        :key="visit.id"
                        class="rounded-2xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] p-5"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-base font-medium">
                                    {{ visit.city.name }}
                                </p>

                                <p class="mt-1 text-sm text-[var(--vyamap-text-muted)]">
                                    {{ visit.city.country }}
                                </p>
                            </div>

                            <span
                                class="rounded-full bg-[var(--vyamap-surface-muted)] px-2.5 py-1 text-xs text-[var(--vyamap-text-muted)]"
                            >
                                {{ visit.city.iso_code }}
                            </span>
                        </div>

                        <!-- Edit form -->
                        <div
                            v-if="editingVisitId === visit.id"
                            class="mt-5 space-y-5"
                        >
                            <!-- Dates -->
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label
                                        :for="`edit-visited-from-${visit.id}`"
                                        class="block text-sm font-medium"
                                    >
                                        From
                                    </label>

                                    <input
                                        :id="`edit-visited-from-${visit.id}`"
                                        v-model="editVisitForm.visited_from"
                                        type="date"
                                        class="mt-2 w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                    />

                                    <p
                                        v-if="editVisitForm.errors.visited_from"
                                        class="mt-2 text-sm text-red-500"
                                    >
                                        {{ editVisitForm.errors.visited_from }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        :for="`edit-visited-until-${visit.id}`"
                                        class="block text-sm font-medium"
                                    >
                                        Until
                                    </label>

                                    <input
                                        :id="`edit-visited-until-${visit.id}`"
                                        v-model="editVisitForm.visited_until"
                                        type="date"
                                        class="mt-2 w-full rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                    />

                                    <p
                                        v-if="editVisitForm.errors.visited_until"
                                        class="mt-2 text-sm text-red-500"
                                    >
                                        {{ editVisitForm.errors.visited_until }}
                                    </p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label
                                    :for="`edit-visit-notes-${visit.id}`"
                                    class="block text-sm font-medium"
                                >
                                    Notes
                                </label>

                                <textarea
                                    :id="`edit-visit-notes-${visit.id}`"
                                    v-model="editVisitForm.notes"
                                    rows="3"
                                    class="mt-2 w-full resize-none rounded-xl border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)] px-4 py-3 text-sm outline-none transition focus:border-[var(--vyamap-text-muted)]"
                                />

                                <p
                                    v-if="editVisitForm.errors.notes"
                                    class="mt-2 text-sm text-red-500"
                                >
                                    {{ editVisitForm.errors.notes }}
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-3">
                                <button
                                    type="button"
                                    class="rounded-xl px-4 py-2 text-sm font-medium text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                                    @click="cancelEditingVisit"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    :disabled="editVisitForm.processing"
                                    class="rounded-xl bg-[var(--vyamap-text)] px-4 py-2 text-sm font-medium text-[var(--vyamap-background)] transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="submitEditVisit"
                                >
                                    {{ editVisitForm.processing ? 'Saving...' : 'Save changes' }}
                                </button>
                            </div>
                        </div>

                        <!-- Visit information -->
                        <div
                            v-if="
                                editingVisitId !== visit.id &&
                                (visit.visited_from || visit.visited_until)
                            "
                            class="mt-4 text-xs text-[var(--vyamap-text-muted)]"
                        > {{
                            formatVisitDateRange(
                                visit.visited_from,
                                visit.visited_until,
                            )
                        }}
                        </div>

                        <p
                            v-if="
                                editingVisitId !== visit.id &&
                                visit.notes
                            "
                            class="mt-3 text-sm leading-6 text-[var(--vyamap-text-muted)]"
                        >
                            {{ visit.notes }}
                        </p>

                        <!-- Edit button -->
                        <div
                            v-if="editingVisitId !== visit.id"
                            class="mt-4 flex justify-end"
                        >
                            <button
                                type="button"
                                class="text-xs font-medium text-[var(--vyamap-text-muted)] transition hover:text-[var(--vyamap-text)]"
                                @click="startEditingVisit(visit)"
                            >
                                Edit
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="props.visits.length === 0"
                    class="mt-4 rounded-2xl border border-dashed border-[var(--vyamap-border)] px-6 py-10 text-center"
                >
                   <p class="text-sm text-[var(--vyamap-text-muted)]">
                        No cities have been added to this trip yet.
                    </p>
                </div>

            </section>

        </div>
    </AppLayout>
</template>