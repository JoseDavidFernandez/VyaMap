<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLayout from '../layouts/AppLayout.vue';
import CountryMap from '../components/dashboard/CountryMap.vue';

interface Visit {
    id: number;
    city_id: number;
    city: string;
    country: string;
    iso_code: string;
    visited_from: string | null;
    visited_until: string | null;
}

interface Trip {
    id: number;
    name: string;
    start_date: string | null;
    end_date: string | null;
}

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
    totalCountries: number;
    years: number[];
    visits: Visit[];
    trips: Trip[];
    flights: Flight[];
}>();

const selectedPeriod = ref<number | 'all'>(
    'all',
);

/*
|--------------------------------------------------------------------------
| Period
|--------------------------------------------------------------------------
*/

const periodLabel = computed(() => {
    return selectedPeriod.value === 'all'
        ? 'All-Time'
        : String(selectedPeriod.value);
});

const periodVisits = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.visits;
    }

    return props.visits.filter((visit) => {
        if (!visit.visited_from) {
            return false;
        }

        return (
            new Date(
                visit.visited_from,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
});

const periodTrips = computed(() => {
    if (selectedPeriod.value === 'all') {
        return props.trips;
    }

    return props.trips.filter((trip) => {
        if (!trip.start_date) {
            return false;
        }

        return (
            new Date(
                trip.start_date,
            ).getFullYear() ===
            selectedPeriod.value
        );
    });
});

const periodFlights = computed(() => {
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
| Countries
|--------------------------------------------------------------------------
*/

const visitedCountries = computed(() => {
    const countries = new Map<
        string,
        {
            id: number;
            name: string;
            iso_code: string;
        }
    >();

    periodVisits.value.forEach((visit) => {
        if (!countries.has(visit.iso_code)) {
            countries.set(visit.iso_code, {
                id: visit.city_id,
                name: visit.country,
                iso_code: visit.iso_code,
            });
        }
    });

    return Array.from(
        countries.values(),
    ).sort((a, b) =>
        a.name.localeCompare(b.name),
    );
});

const visitedCities = computed(() => {
    return new Set(
        periodVisits.value.map(
            (visit) => visit.city_id,
        ),
    ).size;
});

const countriesPercentage = computed(() => {
    if (!props.totalCountries) {
        return 0;
    }

    return (
        (visitedCountries.value.length /
            props.totalCountries) *
        100
    );
});

const countriesRingStyle = computed(() => {
    const percentage = Math.min(
        100,
        countriesPercentage.value,
    );

    return {
        background: `conic-gradient(
            var(--vyamap-text) ${percentage}%,
            var(--vyamap-surface-strong) ${percentage}% 100%
        )`,
    };
});
/*
|--------------------------------------------------------------------------
| Recent visits
|--------------------------------------------------------------------------
*/

const recentVisits = computed(() => {
    return [...periodVisits.value]
        .sort((a, b) => {
            const dateA = a.visited_from
                ? new Date(
                      a.visited_from,
                  ).getTime()
                : 0;

            const dateB = b.visited_from
                ? new Date(
                      b.visited_from,
                  ).getTime()
                : 0;

            return dateB - dateA;
        })
        .slice(0, 5);
});

/*
|--------------------------------------------------------------------------
| Yearly trips
|--------------------------------------------------------------------------
*/

const tripsByYear = computed(() => {
    const groups = new Map<
        number,
        number
    >();

    periodTrips.value.forEach((trip) => {
        if (!trip.start_date) {
            return;
        }

        const year = new Date(
            trip.start_date,
        ).getFullYear();

        groups.set(
            year,
            (groups.get(year) ?? 0) + 1,
        );
    });

    return Array.from(
        groups.entries(),
    )
        .map(([year, count]) => ({
            year,
            count,
        }))
        .sort((a, b) => b.year - a.year);
});

const maxTripsByYear = computed(() => {
    return Math.max(
        1,
        ...tripsByYear.value.map(
            (item) => item.count,
        ),
    );
});

/*
|--------------------------------------------------------------------------
| Flight statistics
|--------------------------------------------------------------------------
*/

const airportCount = computed(() => {
    const airports = new Set<string>();

    periodFlights.value.forEach(
        (flight) => {
            airports.add(
                flight.origin.code ||
                    flight.origin.airport,
            );

            airports.add(
                flight.destination.code ||
                    flight.destination.airport,
            );
        },
    );

    return airports.size;
});

const airlineCount = computed(() => {
    return new Set(
        periodFlights.value
            .map(
                (flight) =>
                    flight.airline,
            )
            .filter(Boolean),
    ).size;
});

/*
|--------------------------------------------------------------------------
| Flight history
|--------------------------------------------------------------------------
*/

const filteredFlights = computed(() => {
    return [...periodFlights.value]
        .sort((a, b) => {
            const dateA = a.departure
                ? new Date(
                      a.departure,
                  ).getTime()
                : 0;

            const dateB = b.departure
                ? new Date(
                      b.departure,
                  ).getTime()
                : 0;

            return dateB - dateA;
        })
        .slice(0, 5);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const flagEmoji = (
    isoCode: string,
) => {
    return isoCode
        .toUpperCase()
        .split('')
        .map((letter) =>
            String.fromCodePoint(
                letter.charCodeAt(0) +
                    127397,
            ),
        )
        .join('');
};

const formatNumber = (
    value: number,
) => {
    return new Intl.NumberFormat(
        'en-GB',
    ).format(Math.round(value));
};

const formatDistance = (
    value: number,
) => {
    if (value < 1000) {
        return `${formatNumber(
            value,
        )} km`;
    }

    return `${new Intl.NumberFormat(
        'en-GB',
        {
            maximumFractionDigits: 1,
        },
    ).format(value / 1000)}k km`;
};

const formatDate = (
    date: string | null,
) => {
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


</script>

<template>
    <AppLayout title="Passport">
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
                        <p
                            class="vyamap-eyebrow"
                        >
                            Your travel history
                        </p>

                        <div
                            class="mt-3 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                        >
                            <div>
                                <h1
                                    class="text-5xl font-semibold tracking-[-0.065em] sm:text-6xl lg:text-7xl"
                                >
                                    Passport
                                </h1>

                                <p
                                    class="mt-4 max-w-2xl text-sm leading-6 vyamap-muted sm:text-base"
                                >
                                    A visual record of
                                    the places you have
                                    visited and the
                                    journeys you have
                                    taken.
                                </p>
                            </div>

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
                                    v-for="year in props.years"
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
                     MAP + PASSPORT
                ====================================================== -->

                <section
                    class="mb-7 grid gap-7 lg:grid-cols-[7fr_3fr]"
                >
                    <!-- MAP -->

                    <div
                        class="vyamap-card-lg overflow-hidden"
                    >
                        <div
                            class="px-6 pt-6 sm:px-7 sm:pt-7"
                        >
                            <p
                                class="vyamap-section-title"
                            >
                                {{ periodLabel }}
                            </p>

                            <h2
                                class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                World explored
                            </h2>
                        </div>

                        <div
                            class="mt-5 h-[500px] overflow-hidden"
                        >
                            <CountryMap
                                :countries="
                                    visitedCountries
                                "
                            />
                        </div>
                    </div>

                    <!-- PASSPORT -->

                    <div
                        class="vyamap-card-lg flex min-h-[500px] flex-col p-7 sm:p-8"
                    >
                        <div>
                            <p
                                class="vyamap-eyebrow"
                            >
                                Travel passport
                            </p>

                            <h2
                                class="mt-3 text-4xl font-semibold tracking-[-0.06em]"
                            >
                                Passport
                            </h2>
                        </div>

                        <div
                            v-if="
                                visitedCountries.length
                            "
                            class="mt-9 flex flex-wrap content-start gap-x-5 gap-y-5"
                        >
                            <span
                                v-for="country in visitedCountries"
                                :key="
                                    country.iso_code
                                "
                                class="cursor-default text-[30px] leading-none transition duration-200 hover:scale-110"
                                :title="
                                    country.name
                                "
                            >
                                {{
                                    flagEmoji(
                                        country.iso_code,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-else
                            class="flex flex-1 items-center justify-center"
                        >
                            <p
                                class="text-sm vyamap-text-subtle"
                            >
                                No passport stamps
                                yet.
                            </p>
                        </div>

                        <div
                            class="mt-auto border-t border-[var(--vyamap-border)] pt-6"
                        >
                            <div
                                class="flex items-center gap-5"
                            >
                                <!-- Countries progress -->
                                <div
                                    class="relative h-20 w-20 shrink-0 rounded-full p-[5px]"
                                    :style="countriesRingStyle"
                                >
                                    <div
                                        class="flex h-full w-full items-center justify-center rounded-full bg-[var(--vyamap-background)]"
                                    >
                                        <span
                                            class="text-sm font-semibold tracking-tight"
                                        >
                                            {{
                                                countriesPercentage.toFixed(
                                                    1,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </div>

                                <!-- Counter -->
                                <div>
                                    <div
                                        class="text-2xl font-semibold tracking-tight"
                                    >
                                        {{
                                            visitedCountries.length
                                        }}
                                        <span
                                            class="text-sm font-normal vyamap-muted"
                                        >
                                            /
                                            {{
                                                props.totalCountries
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 text-xs vyamap-muted"
                                    >
                                        countries explored
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     RECENT VISITS + TRIPS BY YEAR
                ====================================================== -->

                <section class="mb-7">
                    <div
                        class="grid gap-7 lg:grid-cols-[0.8fr_1.2fr]"
                    >
                        <!-- RECENT VISITS -->

                        <div
                            class="vyamap-card-lg p-6 sm:p-8"
                        >
                            <div
                                class="flex items-end justify-between gap-4"
                            >
                                <div>
                                    <p
                                        class="vyamap-section-title"
                                    >
                                        Recent
                                    </p>

                                    <h2
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        Últimos visitados
                                    </h2>
                                </div>

                                <span
                                    class="text-xs vyamap-muted"
                                >
                                    {{
                                        recentVisits.length
                                    }}
                                </span>
                            </div>

                            <div
                                v-if="
                                    recentVisits.length
                                "
                                class="mt-7 divide-y divide-[var(--vyamap-border)]"
                            >
                                <div
                                    v-for="visit in recentVisits"
                                    :key="
                                        visit.id
                                    "
                                    class="flex items-center gap-3 py-4 first:pt-0 last:pb-0"
                                >
                                    <span
                                        class="text-2xl leading-none"
                                    >
                                        {{
                                            flagEmoji(
                                                visit.iso_code,
                                            )
                                        }}
                                    </span>

                                    <div
                                        class="min-w-0 flex-1"
                                    >
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{
                                                visit.city
                                            }}
                                        </div>

                                        <div
                                            class="mt-1 truncate text-xs vyamap-muted"
                                        >
                                            {{
                                                visit.country
                                            }}
                                        </div>
                                    </div>

                                    <div
                                        class="shrink-0 text-right text-[11px] vyamap-text-subtle"
                                    >
                                        {{
                                            formatDate(
                                                visit.visited_from,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-7 border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <p
                                    class="text-sm vyamap-text-subtle"
                                >
                                    No visits recorded
                                    yet.
                                </p>
                            </div>
                        </div>

                        <!-- TRIPS BY YEAR -->

                        <div
                            class="vyamap-card-lg p-6 sm:p-8"
                        >
                            <div
                                class="flex items-end justify-between gap-4"
                            >
                                <div>
                                    <p
                                        class="vyamap-section-title"
                                    >
                                        Travel history
                                    </p>

                                    <h2
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        Trips by year
                                    </h2>
                                </div>

                                <span
                                    class="text-xs vyamap-muted"
                                >
                                    {{
                                        periodTrips.length
                                    }}
                                    trips
                                </span>
                            </div>

                            <div
                                v-if="
                                    tripsByYear.length
                                "
                                class="mt-9 space-y-6"
                            >
                                <div
                                    v-for="item in tripsByYear"
                                    :key="
                                        item.year
                                    "
                                >
                                    <div
                                        class="mb-2 flex items-center justify-between"
                                    >
                                        <span
                                            class="text-sm font-medium"
                                        >
                                            {{
                                                item.year
                                            }}
                                        </span>

                                        <span
                                            class="text-xs vyamap-muted"
                                        >
                                            {{
                                                item.count
                                            }}
                                            {{
                                                item.count ===
                                                1
                                                    ? 'trip'
                                                    : 'trips'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-[var(--vyamap-surface-strong)]"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[var(--vyamap-text)] transition-all duration-500"
                                            :style="{
                                                width: `${(item.count / maxTripsByYear) * 100}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-7 border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <p
                                    class="text-sm vyamap-text-subtle"
                                >
                                    No trips recorded
                                    yet.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- =====================================================
                     FLIGHT HISTORY + STATISTICS
                ====================================================== -->

                <section>
                    <div
                        class="grid gap-7 lg:grid-cols-[1.55fr_0.65fr]"
                    >
                        <!-- FLIGHT HISTORY -->

                        <div
                            class="vyamap-card-lg p-6 sm:p-8"
                        >
                            <div
                                class="mb-7 flex items-end justify-between gap-6"
                            >
                                <div>
                                    <p
                                        class="vyamap-section-title"
                                    >
                                        {{ periodLabel }}
                                    </p>

                                    <h2
                                        class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                                    >
                                        Flight History
                                    </h2>
                                </div>

                                <div
                                    class="text-right text-xs vyamap-muted"
                                >
                                    {{
                                        periodFlights.length
                                    }}
                                    {{
                                        periodFlights.length === 1
                                            ? 'flight'
                                            : 'flights'
                                    }}
                                </div>
                            </div>

                            <div
                                v-if="
                                    filteredFlights.length
                                "
                                class="space-y-2"
                            >
                                <div
                                    v-for="flight in filteredFlights"
                                    :key="
                                        flight.id
                                    "
                                    class="vyamap-flight"
                                >
                                    <div
                                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
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
                                                        'Flight'
                                                    }}
                                                </span>
                                            </div>

                                            <div
                                                class="mt-2 text-sm"
                                            >
                                                {{
                                                    flight
                                                        .origin
                                                        .city
                                                }}

                                                <span
                                                    class="mx-2 vyamap-text-subtle"
                                                >
                                                    →
                                                </span>

                                                {{
                                                    flight
                                                        .destination
                                                        .city
                                                }}
                                            </div>
                                        </div>

                                        <div
                                            class="flex items-center gap-6"
                                        >
                                            <div>
                                                <div
                                                    class="vyamap-eyebrow"
                                                >
                                                    Date
                                                </div>

                                                <div
                                                    class="mt-1 text-xs vyamap-muted"
                                                >
                                                    {{
                                                        formatDate(
                                                            flight.departure,
                                                        )
                                                    }}
                                                </div>
                                            </div>

                                            <div
                                                class="hidden sm:block"
                                            >
                                                <div
                                                    class="vyamap-eyebrow"
                                                >
                                                    Distance
                                                </div>

                                                <div
                                                    class="mt-1 text-xs vyamap-muted"
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

                            <div
                                v-else
                                class="border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <p
                                    class="text-sm vyamap-text-subtle"
                                >
                                    No flights recorded
                                    in this period.
                                </p>
                            </div>
                        </div>

                        <!-- STATISTICS -->

                        <div
                            class="vyamap-card-lg p-6 sm:p-8"
                        >
                            <p
                                class="vyamap-section-title"
                            >
                                Overview
                            </p>

                            <h2
                                class="mt-2 text-3xl font-semibold tracking-[-0.05em]"
                            >
                                Statistics
                            </h2>

                            <div
                                class="mt-8 grid grid-cols-2 gap-x-6 gap-y-8"
                            >
                                <div>
                                    <div
                                        class="text-4xl font-semibold tracking-[-0.04em]"
                                    >
                                        {{
                                            periodFlights.length
                                        }}
                                    </div>

                                    <p
                                        class="mt-1 text-xs vyamap-muted"
                                    >
                                        Flights
                                    </p>
                                </div>

                                <div>
                                    <div
                                        class="text-4xl font-semibold tracking-[-0.04em]"
                                    >
                                        {{
                                            visitedCities
                                        }}
                                    </div>

                                    <p
                                        class="mt-1 text-xs vyamap-muted"
                                    >
                                        Cities
                                    </p>
                                </div>

                                <div>
                                    <div
                                        class="text-4xl font-semibold tracking-[-0.04em]"
                                    >
                                        {{
                                            airportCount
                                        }}
                                    </div>

                                    <p
                                        class="mt-1 text-xs vyamap-muted"
                                    >
                                        Airports
                                    </p>
                                </div>

                                <div>
                                    <div
                                        class="text-4xl font-semibold tracking-[-0.04em]"
                                    >
                                        {{
                                            airlineCount
                                        }}
                                    </div>

                                    <p
                                        class="mt-1 text-xs vyamap-muted"
                                    >
                                        Airlines
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-10 border-t border-[var(--vyamap-border)] pt-6"
                            >
                                <div
                                    class="flex items-end justify-between"
                                >
                                    <div>
                                        <div
                                            class="text-2xl font-semibold"
                                        >
                                            {{
                                                formatNumber(
                                                    periodFlights.reduce(
                                                        (
                                                            total,
                                                            flight,
                                                        ) =>
                                                            total +
                                                            flight.distance_km,
                                                        0,
                                                    ),
                                                )
                                            }}
                                            km
                                        </div>

                                        <p
                                            class="mt-1 text-xs vyamap-muted"
                                        >
                                            Distance flown
                                        </p>
                                    </div>

                                    <div
                                        class="text-right"
                                    >
                                        <div
                                            class="text-2xl font-semibold"
                                        >
                                            {{
                                                periodFlights.reduce(
                                                    (
                                                        total,
                                                        flight,
                                                    ) =>
                                                        total +
                                                        (flight.duration_minutes ??
                                                            0),
                                                    0,
                                                )
                                            }}m
                                        </div>

                                        <p
                                            class="mt-1 text-xs vyamap-muted"
                                        >
                                            Flight time
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>