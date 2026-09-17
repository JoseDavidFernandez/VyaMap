<script setup lang="ts">
import { ref } from 'vue';
import TripCounter from '../components/TripCounter.vue';

const trips = ref(1);
</script>

<template>
    <main>
        <h1>VyaMap</h1>

        <p>
            A personal space to build, visualize and explore your travel history.
        </p>

        <TripCounter :trips="trips" />

        <button type="button" @click="trips++">
            Add trip
        </button>
    </main>
</template>