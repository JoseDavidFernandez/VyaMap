<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import TripFormFields from '../../components/trips/TripFormFields.vue';
import TripFlightSuggestions from '../../components/trips/TripFlightSuggestions.vue';

const form = useForm({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
    // Vuelos independientes sugeridos y aceptados (se asocian al guardar).
    flight_ids: [] as number[],
});

const submit = () => {
    form.post('/trips');
};

// Error de algún vuelo aceptado (p. ej. ya asociado a otro viaje entretanto).
const flightIdsError = computed(() =>
    Object.entries(form.errors as Record<string, string>).find(([key]) => key.startsWith('flight_ids'))?.[1] ?? '',
);
</script>

<template>
    <AppLayout title="New trip">
        <div class="vyamap-container max-w-3xl">
            <Link href="/trips" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-4">
                <Icon name="arrow-left" :size="16" />
                Trips
            </Link>

            <header class="vyamap-page-header !mb-6">
                <div>
                    <h1 class="vyamap-page-title">Create a trip</h1>
                    <p class="vyamap-page-description">
                        Start building your travel history. You can add cities, flights and photos once the trip exists.
                    </p>
                </div>
            </header>

            <form class="vyamap-card p-5 sm:p-7" @submit.prevent="submit">
                <TripFormFields :form="form" />

                <TripFlightSuggestions v-model="form.flight_ids" :start-date="form.start_date" :end-date="form.end_date" />
                <p v-if="flightIdsError" class="vyamap-error">{{ flightIdsError }}</p>

                <div class="mt-7 flex flex-col-reverse gap-3 border-t border-[var(--vyamap-border)] pt-5 sm:flex-row sm:justify-end">
                    <Link href="/trips" class="vyamap-button-secondary">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="vyamap-button">
                        {{ form.processing ? 'Creating...' : 'Create trip' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
