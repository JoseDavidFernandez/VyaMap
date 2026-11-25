<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { useAcceptedFlights } from '../../composables/useAcceptedFlights';
import AppLayout from '../../layouts/AppLayout.vue';
import Icon from '../../components/ui/Icon.vue';
import TripFormFields from '../../components/trips/TripFormFields.vue';
import TripFlightSuggestions from '../../components/trips/TripFlightSuggestions.vue';
import { tripPath } from '../../utils/routes';

interface Trip {
    id: number;
    name: string;
    description: string | null;
    start_date: string;
    end_date: string;
}

const props = defineProps<{
    trip: Trip;
}>();

const form = useForm({
    name: props.trip.name,
    description: props.trip.description ?? '',
    start_date: props.trip.start_date,
    end_date: props.trip.end_date,
    // Vuelos independientes sugeridos y aceptados (se asocian al guardar).
    flight_ids: [] as number[],
});

// Ref de plantilla de TripFlightSuggestions (ref="flightSuggestions").
const { suggestions: flightSuggestions, rememberSent, onError, flightIdsError } = useAcceptedFlights(form);

const submit = () => {
    rememberSent();
    form.put(`/trips/${props.trip.id}`, { onError });
};

</script>

<template>
    <AppLayout :title="`Edit ${trip.name}`">
        <div class="vyamap-container max-w-3xl">
            <Link :href="tripPath(trip)" class="vyamap-button-ghost vyamap-button-sm -ml-3 mb-4">
                <Icon name="arrow-left" :size="16" />
                {{ trip.name }}
            </Link>

            <header class="vyamap-page-header !mb-6">
                <div>
                    <h1 class="vyamap-page-title">Edit trip</h1>
                    <p class="vyamap-page-description">Update the information of your trip.</p>
                </div>
            </header>

            <form class="vyamap-card p-5 sm:p-7" @submit.prevent="submit">
                <TripFormFields :form="form" name-placeholder="e.g. Italy 2026" />

                <TripFlightSuggestions ref="flightSuggestions" v-model="form.flight_ids" :start-date="form.start_date" :end-date="form.end_date" />
                <p v-if="flightIdsError" class="vyamap-error">{{ flightIdsError }}</p>

                <div class="mt-7 flex flex-col-reverse gap-3 border-t border-[var(--vyamap-border)] pt-5 sm:flex-row sm:justify-end">
                    <Link :href="tripPath(trip)" class="vyamap-button-secondary">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="vyamap-button">
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
