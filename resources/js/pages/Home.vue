<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import StatCard from '../components/dashboard/StatCard.vue';
import RecentTrips from '../components/dashboard/RecentTrips.vue';
import AppLayout from '../layouts/AppLayout.vue';


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

const props = defineProps<{
    stats: DashboardStats;
    trips: Trip[];
}>();

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <AppLayout title="Dashboard"></AppLayout>
        <main>
            <h1>VyaMap</h1>

            <p>
                A personal space to build, visualize and explore your travel history.
            </p>

            <section>
                <StatCard
                    :value="props.stats.countries"
                    label="Countries visited"
                />

                <StatCard
                    :value="props.stats.cities"
                    label="Cities visited"
                />

                <StatCard
                    :value="props.stats.trips"
                    label="Trips"
                />

                <StatCard
                    :value="props.stats.flights"
                    label="Flights"
                />
            </section>

            <RecentTrips :trips="props.trips" />


            <button type="button" @click="logout">
                Logout
            </button>
        </main>
    </<AppLayout>
</template>