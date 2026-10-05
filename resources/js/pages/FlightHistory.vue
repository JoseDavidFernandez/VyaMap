<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import TravelMap from '../components/dashboard/TravelMap.vue';

interface AirportPoint {
    code: string | null;
    airport: string;
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
    distance_km: number;
    duration_minutes: number | null;
    origin: AirportPoint;
    destination: AirportPoint;
}

const props = defineProps<{
    flights: Flight[];
}>();

const selectedPeriod = ref<number | 'all'>('all');

/*
|--------------------------------------------------------------------------
| Periods
|--------------------------------------------------------------------------
*/

const years = computed(() => {
    return Array.from(
        new Set(
            props.flights
                .filter((flight) => flight.departure)
                .map((flight) =>
                    new Date(
                        flight.departure!,
                    ).getFullYear(),
                ),
        ),
    ).sort((a, b) => b - a);
});

const filteredFlights = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.flights;
    }

    return props.flights.filter((flight) => {
        if (!flight.departure) {
            return false;
        }

        return (
            new Date(
                flight.departure,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
});

/*
|--------------------------------------------------------------------------
| Map
|--------------------------------------------------------------------------
*/

const mapFlights = computed(() => {
    return filteredFlights.value.map((flight) => ({
        id: flight.id,
        flight_number: flight.flight_number,
        airline: flight.airline,
        departure: flight.departure,
        arrival: flight.arrival,
        origin: {
            id: flight.id,
            name: flight.origin.airport,
            city: flight.origin.city,
            latitude: flight.origin.latitude,
            longitude: flight.origin.longitude,
        },
        destination: {
            id: flight.id,
            name: flight.destination.airport,
            city: flight.destination.city,
            latitude: flight.destination.latitude,
            longitude: flight.destination.longitude,
        },
    }));
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    ).format(new Date(date));
};

const formatDistance = (distance: number) => {
    if (distance < 1000) {
        return `${distance.toLocaleString(
            'en-GB',
        )} km`;
    }

    return `${new Intl.NumberFormat(
        'en-GB',
        {
            maximumFractionDigits: 1,
        },
    ).format(distance / 1000)}k km`;
};
</script>

<template>
    <AppLayout title="Flight History">
        <div class="vyamap-page">
            <div
                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"
            >
                <!-- =====================================================
                     HEADER
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="vyamap-card-lg p-7 sm:p-9 lg:p-10"
                    >
                        <p class="vyamap-eyebrow">
                            Travel history
                        </p>

                        <div
                            class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div>
                                <h1
                                    class="text-5xl font-semibold tracking-[-0.065em] sm:text-6xl lg:text-7xl"
                                >
                                    Flight History
                                </h1>

                                <p
                                    class="mt-4 max-w-2xl text-sm leading-6 vyamap-muted sm:text-base"
                                >
                                    A complete record of every
                                    flight you have added to
                                    VyaMap.
                                </p>
                            </div>

                            <!-- Period selector -->

                            <div
                                class="flex gap-2 overflow-x-auto pb-1 lg:pb-0"
                            >
                                <button
                                    type="button"
                                    class="rounded-full border px-4 py-2 text-sm transition"
                                    :class="
                                        selectedPeriod ===
                                        'all'
                                            ? 'border-[var(--vyamap-border-strong)] bg-[var(--vyamap-surface-strong)] text-white'
                                            : 'border-transparent text-white/40 hover:text-white'
                                    "
                                    @click="
                                        selectedPeriod =
                                            'all'
                                    "
                                >
                                    All-Time
                                </button>

                                <button
                                    v-for="year in years"
                                    :key="year"
                                    type="button"
                                    class="rounded-full border px-4 py-2 text-sm transition"
                                    :class="
                                        selectedPeriod ===
                                        year
                                            ? 'border-[var(--vyamap-border-strong)] bg-[var(--vyamap-surface-strong)] text-white'
                                            : 'border-transparent text-white/40 hover:text-white'
                                    "
                                    @click="
                                        selectedPeriod =
                                            year
                                    "
                                >
                                    {{ year }}
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     MAP
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="vyamap-card-lg overflow-hidden"
                    >
                        <div
                            class="flex flex-col gap-2 px-6 pt-6 sm:px-7 sm:pt-7"
                        >
                            <div
                                class="flex items-end justify-between gap-4"
                            >
                                <div>
                                    <p
                                        class="vyamap-section-title"
                                    >
                                        {{
                                            selectedPeriod ===
                                            'all'
                                                ? 'All-Time'
                                                : selectedPeriod
                                        }}
                                    </p>

                                    <h2
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        Flights map
                                    </h2>
                                </div>

                                <div
                                    class="text-right text-xs vyamap-muted"
                                >
                                    {{
                                        filteredFlights.length
                                    }}
                                    {{
                                        filteredFlights.length ===
                                        1
                                            ? 'flight'
                                            : 'flights'
                                    }}
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-5 h-[520px] overflow-hidden"
                        >
                            <TravelMap
                                :cities="[]"
                                :flights="mapFlights"
                            />
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     FLIGHT LIST
                ====================================================== -->

                <section>
                    <div
                        class="vyamap-card-lg overflow-hidden"
                    >
                        <!-- List header -->

                        <div
                            class="flex items-end justify-between gap-4 px-6 py-6 sm:px-7"
                        >
                            <div>
                                <p
                                    class="vyamap-section-title"
                                >
                                    {{
                                        selectedPeriod ===
                                        'all'
                                            ? 'All-Time'
                                            : selectedPeriod
                                    }}
                                </p>

                                <h2
                                    class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                >
                                    Flight history
                                </h2>
                            </div>

                            <div
                                class="text-right text-xs vyamap-muted"
                            >
                                {{
                                    filteredFlights.length
                                }}
                                {{
                                    filteredFlights.length ===
                                    1
                                        ? 'flight'
                                        : 'flights'
                                }}
                            </div>
                        </div>

                        <!-- Column labels -->

                        <div
                            v-if="filteredFlights.length"
                            class="hidden border-y border-[var(--vyamap-border)] px-6 py-3 text-[10px] uppercase tracking-[0.16em] vyamap-text-subtle sm:grid sm:grid-cols-[1.1fr_1.8fr_1.1fr_0.9fr_0.8fr] sm:gap-5 sm:px-7"
                        >
                            <span>
                                Flight
                            </span>

                            <span>
                                Route
                            </span>

                            <span>
                                Airline
                            </span>

                            <span>
                                Date
                            </span>

                            <span class="text-right">
                                Distance
                            </span>
                        </div>

                        <!-- Rows -->

                        <div
                            v-if="filteredFlights.length"
                            class="divide-y divide-[var(--vyamap-border)]"
                        >
                            <div
                                v-for="flight in filteredFlights"
                                :key="flight.id"
                                class="px-6 py-5 transition hover:bg-[var(--vyamap-surface-muted)] sm:px-7"
                            >
                                <!-- Desktop -->

                                <div
                                    class="hidden items-center sm:grid sm:grid-cols-[1.1fr_1.8fr_1.1fr_0.9fr_0.8fr] sm:gap-5"
                                >
                                    <!-- Flight -->

                                    <div
                                        class="min-w-0"
                                    >
                                        <div
                                            class="truncate text-sm font-semibold"
                                        >
                                            {{
                                                flight.flight_number
                                            }}
                                        </div>

                                        <div
                                            v-if="flight.origin.code || flight.destination.code"
                                            class="mt-1 text-[11px] vyamap-text-subtle"
                                        >
                                            {{
                                                flight.origin.code ||
                                                '—'
                                            }}
                                            →
                                            {{
                                                flight.destination.code ||
                                                '—'
                                            }}
                                        </div>
                                    </div>

                                    <!-- Route -->

                                    <div
                                        class="min-w-0"
                                    >
                                        <div
                                            class="truncate text-sm"
                                        >
                                            {{
                                                flight.origin.city
                                            }}

                                            <span
                                                class="mx-2 vyamap-text-subtle"
                                            >
                                                →
                                            </span>

                                            {{
                                                flight.destination.city
                                            }}
                                        </div>
                                    </div>

                                    <!-- Airline -->

                                    <div
                                        class="truncate text-sm vyamap-muted"
                                    >
                                        {{
                                            flight.airline ||
                                            '—'
                                        }}
                                    </div>

                                    <!-- Date -->

                                    <div
                                        class="text-xs vyamap-muted"
                                    >
                                        {{
                                            formatDate(
                                                flight.departure,
                                            )
                                        }}
                                    </div>

                                    <!-- Distance -->

                                    <div
                                        class="text-right text-xs vyamap-muted"
                                    >
                                        {{
                                            formatDistance(
                                                flight.distance_km,
                                            )
                                        }}
                                    </div>
                                </div>

                                <!-- Mobile -->

                                <div
                                    class="sm:hidden"
                                >
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div
                                            class="min-w-0"
                                        >
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm font-semibold"
                                                >
                                                    {{
                                                        flight.flight_number
                                                    }}
                                                </span>

                                                <span
                                                    class="text-xs vyamap-text-subtle"
                                                >
                                                    {{
                                                        flight.airline ||
                                                        '—'
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                class="mt-2 text-sm"
                                            >
                                                {{
                                                    flight.origin.city
                                                }}

                                                <span
                                                    class="mx-2 vyamap-text-subtle"
                                                >
                                                    →
                                                </span>

                                                {{
                                                    flight.destination.city
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 text-[11px] vyamap-text-subtle"
                                            >
                                                {{
                                                    flight.origin.code ||
                                                    '—'
                                                }}
                                                →
                                                {{
                                                    flight.destination.code ||
                                                    '—'
                                                }}
                                            </div>
                                        </div>

                                        <div
                                            class="shrink-0 text-right"
                                        >
                                            <div
                                                class="text-xs vyamap-muted"
                                            >
                                                {{
                                                    formatDate(
                                                        flight.departure,
                                                    )
                                                }}
                                            </div>

                                            <div
                                                class="mt-1 text-xs vyamap-text-subtle"
                                            >
                                                {{
                                                    formatDistance(
                                                        flight.distance_km,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->

                        <div
                            v-else
                            class="border-t border-[var(--vyamap-border)] px-6 py-8 sm:px-7"
                        >
                            <p
                                class="text-sm vyamap-text-subtle"
                            >
                                No flights recorded in this
                                period.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>