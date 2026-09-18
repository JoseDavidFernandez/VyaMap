<script setup lang="ts">
interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

defineProps<{
    trips: Trip[];
}>();
</script>

<template>
    <section>
        <h2>Recent trips</h2>

        <div v-if="trips.length === 0">
            <p>No trips yet.</p>
        </div>

        <ul v-else>
            <li v-for="trip in trips" :key="trip.id">
                <strong>{{ trip.name }}</strong>

                <span>
                    {{ trip.start_date }} → {{ trip.end_date }}
                </span>

                <p v-if="trip.description">
                    {{ trip.description }}
                </p>
            </li>
        </ul>
    </section>
</template>