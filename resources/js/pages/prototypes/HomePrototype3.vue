<script setup lang="ts">

import AppLayout from '../../layouts/AppLayout.vue';

import CountryMap from '../../components/dashboard/CountryMap.vue';

import TravelMap from '../../components/dashboard/TravelMap.vue';



import { Link } from '@inertiajs/vue3';

import { computed, ref } from 'vue';



interface User {

    name: string;

}



interface Stats {

    countries: number;

    cities: number;

    trips: number;

    flights: number;

}



interface NextTrip {

    id: number;

    name: string;

    description: string | null;

    start_date: string | null;

    end_date: string | null;

    days_until: number;

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



interface RecentTrip {

    id: number;

    name: string;

    description: string | null;

    start_date: string | null;

    end_date: string | null;

    cities: string[];

    countries: string[];

}



interface Photo {

    id: number;

    path: string;

    thumbnail_path: string | null;

    original_filename: string;

}



interface TripsByYear {

    year: number;

    count: number;

}



const props = defineProps<{

    user: User;

    stats: Stats;

    nextTrip: NextTrip | null;

    map: {

        visits: Visit[];

        flights: Flight[];

    };

    recentFlights: Flight[];

    recentTrips: RecentTrip[];

    recentPhotos: Photo[];

    tripsByYear: TripsByYear[];

}>();



const formatDate = (date: string | null) => {

    if (!date) {

        return '—';

    }



    return new Intl.DateTimeFormat('en-GB', {

        day: '2-digit',

        month: 'short',

        year: 'numeric',

    }).format(new Date(`${date}T00:00:00`));

};



const formatDateShort = (date: string | null) => {

    if (!date) {

        return '—';

    }



    return new Intl.DateTimeFormat('en-GB', {

        day: '2-digit',

        month: 'short',

    }).format(new Date(`${date}T00:00:00`));

};



const formatDateTime = (date: string | null) => {

    if (!date) {

        return '—';

    }



    return new Intl.DateTimeFormat('en-GB', {

        day: '2-digit',

        month: 'short',

        hour: '2-digit',

        minute: '2-digit',

    }).format(new Date(date));

};



const nextTripLabel = computed(() => {

    if (!props.nextTrip) {

        return 'No upcoming trips';

    }



    if (props.nextTrip.days_until === 0) {

        return 'Today';

    }



    if (props.nextTrip.days_until === 1) {

        return 'Tomorrow';

    }



    return `In ${props.nextTrip.days_until} days`;

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



    props.map.visits.forEach((visit) => {

        if (!countries.has(visit.city.iso_code)) {

            countries.set(visit.city.iso_code, {

                id: visit.city.id,

                name: visit.city.country,

                iso_code: visit.city.iso_code,

            });

        }

    });



    return Array.from(countries.values());

});



/*

|--------------------------------------------------------------------------

| Countries overview

|--------------------------------------------------------------------------

*/



const totalCountries: number = 251;



const countriesPercentage = computed(() => {

    return Math.round(

        (props.stats.countries / totalCountries) * 100,

    );

});



const countriesRingStyle = computed(() => {

    const percentage = Math.min(

        100,

        Math.max(

            0,

            (props.stats.countries / totalCountries) * 100,

        ),

    );



    return {

        background: `conic-gradient(

            rgba(255,255,255,0.16) 0% ${percentage}%,

            rgba(255,255,255,0.04) ${percentage}% 100%

        )`,

    };

});



/*

|--------------------------------------------------------------------------

| Trips by year

|--------------------------------------------------------------------------

*/



const maxTripsByYear = computed(() => {

    return Math.max(

        1,

        ...props.tripsByYear.map((item) => item.count),

    );

});



/*

|--------------------------------------------------------------------------

| Photos

|--------------------------------------------------------------------------

*/



const isPhotosHovered = ref(false);



const photoPositions = computed(() => {

    const count = Math.min(props.recentPhotos.length, 7);



    if (count === 0) {

        return [];

    }



    const spacing = 100;

    const center = (count - 1) / 2;



    return Array.from({ length: count }, (_, index) => {

        return (index - center) * spacing;

    });

});



const photoHoverPositions = computed(() => {

    const count = Math.min(props.recentPhotos.length, 7);



    if (count === 0) {

        return [];

    }



    const spacing = 140;

    const center = (count - 1) / 2;



    return Array.from({ length: count }, (_, index) => {

        return (index - center) * spacing;

    });

});





</script>



<template>

    <AppLayout title="Dashboard">

        <div class="vyamap-page">

            <main

                class="mx-auto max-w-[var(--vyamap-content-width)] px-5 pb-20 pt-8 sm:px-8 lg:px-10"

            >

                <!-- =====================================================

                     WELCOME / NEXT TRIP

                ====================================================== -->



                <section class="mb-7">

                    <div class="vyamap-card-lg overflow-hidden">

                        <div class="grid lg:grid-cols-[1.2fr_0.8fr]">



                            <!-- Welcome -->



                            <div class="p-7 sm:p-9 lg:p-10">

                                <div class="vyamap-eyebrow">

                                    VyaMap

                                </div>



                                <h1

                                    class="mt-3 max-w-3xl text-4xl font-semibold tracking-[-0.06em] sm:text-5xl lg:text-6xl"

                                >

                                    Your travel history,

                                    <br class="hidden sm:block" />

                                    in one place.

                                </h1>



                                <p

                                    class="mt-5 max-w-xl text-sm leading-6 vyamap-muted sm:text-base"

                                >

                                    Build your travel history, explore the places

                                    you've visited and keep your upcoming trips

                                    organized.

                                </p>

                            </div>



                            <!-- Next trip -->

                            <div

                                class="relative flex min-h-[260px] flex-col justify-between overflow-hidden border-t border-[var(--vyamap-border)] bg-gradient-to-br from-cyan-200/[0.08] via-violet-400/[0.06] to-transparent p-7 sm:p-9 lg:border-l lg:border-t-0 lg:p-10"

                            >

                                <div

                                    class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-cyan-300/[0.08] blur-3xl"

/>

                                <div

                                    class="absolute -bottom-20 -left-10 h-52 w-52 rounded-full bg-violet-400/[0.08] blur-3xl"

/>

                                <div class="relative z-10">

                                    <div class="vyamap-section-title">

                                        Next trip

                                    </div>



                                    <div

                                        class="mt-4 text-sm font-medium text-white/45"

                                    >

                                        {{ nextTripLabel }}

                                    </div>

                                </div>



                                <div

                                    v-if="nextTrip"

                                    class="relative z-10"

                                >

                                    <Link

                                        :href="`/trips/${nextTrip.id}`"

                                        class="group block"

                                    >

                                        <h2

                                            class="text-3xl font-semibold tracking-[-0.05em] transition group-hover:text-white/80 sm:text-4xl"

                                        >

                                            {{ nextTrip.name }}

                                        </h2>



                                        <p

                                            class="mt-2 text-xs text-white/30"

                                        >

                                            {{ formatDate(nextTrip.start_date) }}



                                            <span v-if="nextTrip.end_date">

                                                —

                                                {{ formatDate(nextTrip.end_date) }}

                                            </span>

                                        </p>

                                    </Link>

                                </div>



                                <div

                                    v-else

                                    class="relative z-10"

                                >

                                    <h2

                                        class="text-2xl font-semibold tracking-[-0.04em]"

                                    >

                                        Nothing planned yet.

                                    </h2>



                                    <p

                                        class="mt-2 text-xs text-white/30"

                                    >

                                        Your next adventure will appear here.

                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>



                <!-- =====================================================

                     STATS

                ====================================================== -->



                <section class="mb-7">

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

                        <div class="vyamap-stat">

                            <div class="text-3xl font-semibold">

                                {{ stats.countries }}

                            </div>



                            <div class="mt-1 vyamap-eyebrow">

                                Countries

                            </div>

                        </div>

                        <div class="vyamap-stat">

                            <div class="text-3xl font-semibold">

                                {{ stats.cities }}

                            </div>

                            <div class="mt-1 vyamap-eyebrow">

                                Cities

                            </div>

                        </div>

                        <div class="vyamap-stat">

                            <div class="text-3xl font-semibold">

                                {{ stats.trips }}

                            </div>

                            <div class="mt-1 vyamap-eyebrow">

                                Trips

                            </div>

                        </div>

                        <div class="vyamap-stat">

                            <div class="text-3xl font-semibold">

                                {{ stats.flights }}

                            </div>

                            <div class="mt-1 vyamap-eyebrow">

                                Flights

                            </div>

                        </div>

                    </div>

                </section>

                <!-- =====================================================

                     WORLD / COUNTRIES

                ====================================================== -->

                <section

                    class="mb-7 grid gap-7 lg:grid-cols-[2.35fr_1fr]"

                >

                    <!-- WORLD MAP -->



                    <div class="vyamap-map-card overflow-hidden">

                        <div

                            class="flex items-center justify-between border-b border-[var(--vyamap-border)] px-6 py-5 sm:px-7"

                        >

                            <div>

                                <div class="vyamap-section-title">

                                    Exploration

                                </div>



                                <h2

                                    class="vyamap-section-heading"

                                >

                                    Your world

                                </h2>

                            </div>



                            <div class="text-[10px] vyamap-muted">

                                {{ stats.countries }} countries

                            </div>

                        </div>



                        <div class="h-[500px] w-full">

                            <CountryMap

                                :countries="visitedCountries"

                            />

                        </div>

                    </div>



                    <!-- COUNTRIES VISITED -->



                    <div

                        class="vyamap-card-lg flex min-h-[585px] flex-col p-6 sm:p-7"

                    >

                        <div>

                            <div class="vyamap-section-title">

                                Exploration

                            </div>



                            <div class="flex items-end justify-between gap-3">




                                <h2 class="vyamap-section-heading">




                                    Countries visited




                                </h2>




                                <Link




                                    href="/passport"




                                    class="shrink-0 text-[10px] font-medium text-white/35 transition hover:text-white"




                                >




                                    View passport →




                                </Link>




                            </div>

                        </div>



                        <div

                            class="flex flex-1 flex-col justify-center"

                        >

                            <div class="flex justify-center">

                                <div

                                    class="relative flex h-44 w-44 items-center justify-center rounded-full"

                                    :style="countriesRingStyle"

                                >

                                    <div

                                        class="flex h-36 w-36 flex-col items-center justify-center rounded-full bg-[#11151c]"

                                    >

                                        <span

                                            class="text-3xl font-semibold tracking-[-0.05em]"

                                        >

                                            {{ countriesPercentage }}%

                                        </span>

                                        <span

                                            class="mt-1 text-[9px] uppercase tracking-[0.18em] vyamap-text-subtle"

                                        >

                                            visited

                                        </span>

                                    </div>

                                </div>

                            </div>

                            <div

                                class="mt-8 grid grid-cols-2 gap-3"

                            >

                                <div class="vyamap-card p-4">

                                    <div class="text-2xl font-semibold">

                                        {{ stats.countries }}

                                    </div>

                                    <div

                                        class="mt-1 text-[10px] vyamap-text-subtle"

                                    >

                                        Visited

                                    </div>

                                </div>

                                <div class="vyamap-card p-4">

                                    <div class="text-2xl font-semibold">

                                        {{ totalCountries - stats.countries }}

                                    </div>

                                    <div

                                        class="mt-1 text-[10px] vyamap-text-subtle"

                                    >

                                        Remaining

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>





                <!-- =====================================================

                     PHOTOS

                ====================================================== -->



                <section class="mb-7">

                    <div class="relative overflow-hidden rounded-[30px] border border-white/[0.10] bg-gradient-to-br from-cyan-300/[0.10] via-[#171b28] to-[#0d1119] px-6 py-12 sm:px-10 sm:py-14 lg:px-16 lg:py-16">

                        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-cyan-300/[0.07] blur-3xl"></div>

                        <div class="pointer-events-none absolute -bottom-32 -right-20 h-80 w-80 rounded-full bg-violet-400/[0.06] blur-3xl"></div>

                        <div class="relative z-10 flex flex-col items-center text-center">

                            <h2 class="max-w-3xl text-4xl font-semibold leading-[0.95] tracking-[-0.065em] text-white sm:text-5xl lg:text-6xl">

                                A place to relive your

                                <br class="hidden sm:block" />

                                travel memories.

                            </h2>

                            <div v-if="recentPhotos.length" class="relative mt-7 h-[250px] w-full max-w-[850px] sm:mt-9 sm:h-[280px]"

                                @mouseenter="isPhotosHovered = true"

                                @mouseleave="isPhotosHovered = false"

                                >

                                <div

                                    v-for="(photo, index) in recentPhotos.slice(0, 7)"

                                    :key="photo.id"

                                    class="absolute left-1/2 top-1/2 h-[250px] w-[200px] overflow-hidden rounded-[20px] border-[5px] border-white bg-white shadow-2xl transition-all duration-500 ease-out"

                                    :style="{

                                        zIndex: 20 - index,

                                        transform: `translate(-50%, -50%) translateX(${

                                            isPhotosHovered

                                                ? photoHoverPositions[index]

                                                : photoPositions[index]

                                        }px) translateY(${

                                            isPhotosHovered

                                                ? [20, 8, -2, -8, -2, 8, 20][index]

                                                : [18, 8, 0, -4, 0, 8, 18][index]

                                        }px) rotate(${

                                            [-8, -5, -2, 0, 2, 5, 8][index]

                                        }deg)`

                                    }"

                                >

                                    <img

                                        :src="`/storage/${photo.thumbnail_path ?? photo.path}`"

                                        :alt="photo.original_filename"

                                        class="h-full w-full object-cover"

                                        loading="lazy"

                                    />

                                </div>

                            </div>

                            <div v-else class="flex h-[250px] items-center justify-center">

                                <div class="text-sm text-white/30">

                                    Your visual travel history will appear here.

                                </div>

                            </div>

                            <p class="mt-5 max-w-xl text-xs leading-5 text-white/45 sm:text-sm">

                                Keep the places you've visited close.

                                <br class="hidden sm:block" />

                                Your journeys, captured in one place.

                            </p>

                            <div class="mt-6 flex items-center justify-center gap-6">

                                <Link

                                    href="/photos"

                                    class="rounded-full bg-white px-5 py-2.5 text-[11px] font-medium text-[#11151c] transition hover:bg-white/90"

                                >

                                    View all photos

                                </Link>

                                <Link

                                    href="/photos"

                                    class="text-[11px] font-medium text-white/50 transition hover:text-white"

                                >

                                    Explore memories →

                                </Link>

                            </div>

                        </div>

                    </div>

                </section>



                
                <!-- =====================================================
                     TRIPS · PROTOTYPE 3
                ====================================================== -->

                <section class="mb-7">
                    <div class="mb-5 flex items-end justify-between gap-4">
                        <div>
                            <div class="vyamap-section-title">Timeline</div>
                            <h2 class="vyamap-section-heading">Trips</h2>
                        </div>

                        <Link href="/trips" class="text-[10px] text-white/35 hover:text-white">
                            View all →
                        </Link>
                    </div>

                    <div class="overflow-hidden rounded-[26px] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
                        <Link
                            v-for="trip in recentTrips"
                            :key="trip.id"
                            :href="`/trips/${trip.id}`"
                            class="group grid gap-5 border-b border-[var(--vyamap-border)] p-5 last:border-0 transition hover:bg-white/[0.018] sm:grid-cols-[1fr_auto_auto] sm:items-center"
                        >
                            <div>
                                <div class="text-[9px] uppercase tracking-[0.2em] text-white/25">
                                    {{ trip.countries.join(' · ') }}
                                </div>

                                <h3 class="mt-2 text-lg font-semibold tracking-[-0.035em]">
                                    {{ trip.name }}
                                </h3>
                            </div>

                            <div class="hidden text-right sm:block">
                                <div class="text-xs text-white/45">{{ formatDateShort(trip.start_date) }}</div>
                                <div class="mt-1 text-[10px] text-white/25">
                                    {{ trip.cities.length }}
                                    {{ trip.cities.length === 1 ? 'city' : 'cities' }}
                                </div>
                            </div>

                            <span class="text-white/20 transition group-hover:translate-x-1 group-hover:text-white">
                                →
                            </span>
                        </Link>
                    </div>
                </section>

                <section class="mb-7 grid gap-7 lg:grid-cols-[1fr_1fr]">
                    <div class="vyamap-card-lg p-6 sm:p-7">
                        <div class="vyamap-section-title">History</div>
                        <h2 class="vyamap-section-heading">Trips by year</h2>

                        <div class="mt-8 space-y-5">
                            <div v-for="item in tripsByYear" :key="item.year" class="flex items-center gap-3">
                                <span class="w-10 text-[10px] text-white/30">{{ item.year }}</span>

                                <div class="h-1.5 flex-1 rounded-full bg-white/[0.05]">
                                    <div
                                        class="h-full rounded-full bg-white/30"
                                        :style="{ width: `${(item.count / maxTripsByYear) * 100}%` }"
                                    ></div>
                                </div>

                                <span class="w-5 text-right text-xs">{{ item.count }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="vyamap-card-lg p-6 sm:p-7">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <div class="vyamap-section-title">Overview</div>
                                <h2 class="vyamap-section-heading">At a glance</h2>
                            </div>

                            <Link href="/passport" class="text-[10px] text-white/35 hover:text-white">
                                Passport →
                            </Link>
                        </div>

                        <div class="mt-8 grid grid-cols-2 gap-x-8 gap-y-7">
                            <div>
                                <div class="text-3xl font-semibold">{{ stats.countries }}</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.15em] text-white/25">Countries</div>
                            </div>

                            <div>
                                <div class="text-3xl font-semibold">{{ stats.cities }}</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.15em] text-white/25">Cities</div>
                            </div>

                            <div>
                                <div class="text-3xl font-semibold">{{ stats.trips }}</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.15em] text-white/25">Trips</div>
                            </div>

                            <div>
                                <div class="text-3xl font-semibold">{{ stats.flights }}</div>
                                <div class="mt-1 text-[10px] uppercase tracking-[0.15em] text-white/25">Flights</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mb-7">
                    <div class="vyamap-memory">
                        <div class="vyamap-memory-content">
                            <div>
                                <div class="vyamap-memory-label">Travel history</div>
                            </div>

                            <div>
                                <div class="text-2xl font-semibold tracking-[-0.05em]">Keep exploring</div>
                                <p class="mt-2 max-w-xl text-xs leading-5 vyamap-muted">
                                    Your trips, destinations and flights will continue building your travel history here.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="grid gap-7 lg:grid-cols-[1.55fr_0.65fr]">
                        <div class="vyamap-card-lg p-6 sm:p-8">
                            <div class="mb-7 flex items-end justify-between gap-6">
                                <div>
                                    <div class="vyamap-section-title">Transport</div>
                                    <h2 class="vyamap-section-heading">Flight history</h2>
                                </div>

                                <div class="text-right text-xs vyamap-muted">
                                    {{ stats.flights }}
                                    {{ stats.flights === 1 ? 'flight' : 'flights' }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-for="flight in recentFlights" :key="flight.id" class="vyamap-flight">
                                    <div class="flex items-center justify-between gap-5">
                                        <div>
                                            <div class="text-sm font-semibold">{{ flight.flight_number }}</div>
                                            <div v-if="flight.airline" class="mt-1 text-[10px] text-white/30">
                                                {{ flight.airline }}
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-xs">
                                                {{ flight.origin.city }} → {{ flight.destination.city }}
                                            </div>

                                            <div class="mt-1 text-[9px] text-white/25">
                                                {{ formatDateShort(flight.departure) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Link
                                href="/flight-history"
                                class="mt-6 inline-flex text-[10px] text-white/35 hover:text-white"
                            >
                                Open flight history →
                            </Link>
                        </div>

                        <div class="vyamap-map-card overflow-hidden">
                            <div class="border-b border-[var(--vyamap-border)] px-6 py-5">
                                <div class="vyamap-section-title">Routes</div>
                                <h2 class="vyamap-section-heading">Flight map</h2>
                            </div>

                            <div class="h-[500px]">
                                <TravelMap :cities="[]" :flights="map.flights" />
                            </div>
                        </div>
                    </div>
                </section>

            </main>
        </div>
    </AppLayout>
</template>
