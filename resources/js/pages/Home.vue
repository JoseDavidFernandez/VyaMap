<script setup lang="ts">
import TripCounter from '../components/TripCounter.vue';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

const props = defineProps<{
    trips: Trip[];
}>();
</script>

<template>
    <main>
        <h1>VyaMap</h1>

        <p>
            A personal space to build, visualize and explore your travel history.
        </p>

        <TripCounter :trips="props.trips.length" />

        <ul>
            <li v-for="trip in props.trips" :key="trip.id">
                {{ trip.name }}
            </li>
        </ul>
    </main>
</template>