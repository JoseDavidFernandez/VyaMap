<script setup lang="ts">
import StatCard from '../components/dashboard/StatCard.vue';
import RecentTrips from '../components/dashboard/RecentTrips.vue';
import AppLayout from '../layouts/AppLayout.vue';
import TravelMap from '../components/dashboard/TravelMap.vue';

interface DashboardStats {
    countries: number;
    cities: number;
    trips: number;
    flights: number;
}

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

interface MapCity {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
}

interface MapFlight {
    id: number;
    origin: {
        name: string;
        latitude: number;
        longitude: number;
    };
    destination: {
        name: string;
        latitude: number;
        longitude: number;
    };
}

const props = defineProps<{
    stats: DashboardStats;
    trips: Trip[];
    cities: MapCity[];
    flights: MapFlight[];
}>();

</script>

<template>
    <AppLayout title="Dashboard">
        <div class="mx-auto max-w-7xl px-6 py-10">

            <!-- Header -->
            <section class="max-w-2xl">
                <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                    Your journey
                </p>

                <h1 class="mt-2 text-4xl font-semibold tracking-tight text-[var(--vyamap-text)]">
                    My Travel History
                </h1>

                <p class="mt-3 text-base leading-7 text-[var(--vyamap-text-muted)]">
                    A personal space to build, visualize and explore your travel history.
                </p>
            </section>

            <!-- Stats -->
            <section class="mt-10 border-y border-[var(--vyamap-border)] py-6">
                <div class="grid grid-cols-4 divide-x divide-[var(--vyamap-border)]">
                    <div class="px-6 first:pl-0">
                        <StatCard
                            :value="props.stats.countries"
                            label="Countries visited"
                        />
                    </div>

                    <div class="px-6">
                        <StatCard
                            :value="props.stats.cities"
                            label="Cities visited"
                        />
                    </div>

                    <div class="px-6">
                        <StatCard
                            :value="props.stats.trips"
                            label="Trips"
                        />
                    </div>

                    <div class="px-6 last:pr-0">
                        <StatCard
                            :value="props.stats.flights"
                            label="Flights"
                        />
                    </div>
                </div>
            </section>

            <!-- Map -->
            <section class="mt-10">
                <div class="flex items-end justify-between">
                    <div>
                        <p class="text-sm font-medium text-[var(--vyamap-text-muted)]">
                            Explore
                        </p>

                        <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                            Your world
                        </h2>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-[var(--vyamap-radius-xl)] border border-[var(--vyamap-border)] bg-[var(--vyamap-surface)]">
                    <TravelMap
                        :cities="props.cities"
                        :flights="props.flights"
                    />
                </div>
                
            </section>

            <!-- Recent trips -->
            <section class="mt-12">
                <RecentTrips :trips="props.trips" />
            </section>

        </div>
    </AppLayout>
</template>