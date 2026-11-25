<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from '../ui/Icon.vue';
import { flightDepartureLabel } from '../../utils/flightTime';
import { isOutsideDates } from '../../utils/tripDates';
import {
    flightRouteLabel,
    flightSuggestionsUrl,
    loadStandaloneFlights,
    standaloneFlightSearchUrl,
    unavailableFlightIds,
    unavailableFlightsMessage,
} from '../../utils/standaloneFlights';
import type { FlightRecord } from '../../types/flights';

/*
 * Añadir vuelos existentes desde la ficha del viaje. Sin búsqueda muestra las
 * sugerencias (fechas del viaje ±1 día); con texto o fecha busca entre todos
 * los vuelos independientes del usuario: código o ciudad de origen o destino,
 * ruta ("MAD LIS") o número de vuelo. Un vuelo fuera de las fechas del viaje
 * se avisa, no se bloquea (D3).
 */

const props = defineProps<{
    tripId: number;
    startDate: string | null;
    endDate: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const query = ref('');
const date = ref('');
const flights = ref<FlightRecord[]>([]);
const loading = ref(false);
const loadError = ref('');
const attachError = ref('');
const attaching = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const searching = computed(() => query.value.trim() !== '' || date.value !== '');

const load = () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(async () => {
        const current = ++requestId;
        loading.value = true;
        loadError.value = '';

        try {
            const url = searching.value
                ? standaloneFlightSearchUrl(query.value, date.value)
                : props.startDate && props.endDate
                    ? flightSuggestionsUrl(props.startDate, props.endDate)
                    : null;
            const results = url ? await loadStandaloneFlights(url) : [];

            if (current === requestId) {
                flights.value = results;
            }
        } catch {
            if (current === requestId) {
                flights.value = [];
                loadError.value = 'Flight search is not available right now.';
            }
        } finally {
            if (current === requestId) {
                loading.value = false;
            }
        }
    }, 250);
};

watch([query, date], load, { immediate: true });

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const isOutside = (flight: FlightRecord) => isOutsideDates(flight.departure_date, props.startDate, props.endDate);

const attach = (flightIds: number[]) => {
    attachError.value = '';

    router.post(`/trips/${props.tripId}/flights/attach`, { flight_ids: flightIds }, {
        preserveScroll: true,
        onStart: () => {
            attaching.value = true;
        },
        onSuccess: () => {
            flights.value = flights.value.filter((flight) => !flightIds.includes(flight.id));
        },
        onError: (errors) => {
            // Otra pestaña o petición ya lo ha añadido a otro viaje: se avisa y se recarga la lista.
            const { ids } = unavailableFlightIds(errors, flightIds);

            attachError.value = ids.length
                ? unavailableFlightsMessage(ids.length)
                : Object.values(errors)[0] ?? 'The flight could not be added.';

            if (ids.length) {
                load();
            }
        },
        onFinish: () => {
            attaching.value = false;
        },
    });
};
</script>

<template>
    <div data-flight-picker>
        <div class="mb-3 flex items-center justify-between gap-2">
            <h3 class="vyamap-h3">Add an existing flight</h3>
            <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="emit('close')">Close</button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto]">
            <div>
                <label for="flight-picker-query" class="vyamap-label">Route, airport, city or flight number</label>
                <input
                    id="flight-picker-query"
                    v-model="query"
                    type="search"
                    autocomplete="off"
                    maxlength="100"
                    class="vyamap-input"
                    placeholder="MAD LIS, Lisbon or IB3456"
                />
            </div>
            <div>
                <label for="flight-picker-date" class="vyamap-label">Date</label>
                <input id="flight-picker-date" v-model="date" type="date" class="vyamap-input" />
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <p class="vyamap-help !mt-0">
                {{ searching ? 'Your flights without a trip that match.' : 'Your flights without a trip on these dates, one day either side.' }}
            </p>
            <button
                v-if="!searching && flights.length > 1"
                type="button"
                class="vyamap-button-secondary vyamap-button-sm"
                :disabled="attaching"
                @click="attach(flights.map((flight) => flight.id))"
            >
                Add all
            </button>
        </div>

        <p v-if="loadError" class="vyamap-error">{{ loadError }}</p>
        <p v-if="attachError" class="vyamap-error">{{ attachError }}</p>

        <ul v-if="flights.length" class="mt-2 flex flex-col gap-2">
            <li
                v-for="flight in flights"
                :key="flight.id"
                class="vyamap-flight flex flex-wrap items-center justify-between gap-3"
                :data-picker-flight="flight.id"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ flightRouteLabel(flight) }}</p>
                    <p class="text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                        <span class="vyamap-number">{{ flightDepartureLabel(flight) }}</span>
                        <span v-if="flight.origin.city && flight.destination.city"> · {{ flight.origin.city }} → {{ flight.destination.city }}</span>
                    </p>
                    <p v-if="isOutside(flight)" class="text-[0.8125rem] text-[var(--vyamap-warning)]" data-outside-warning>
                        Outside the trip dates. It can still be added.
                    </p>
                </div>
                <button type="button" class="vyamap-button-secondary vyamap-button-sm" :disabled="attaching" @click="attach([flight.id])">
                    <Icon name="plus" :size="16" />
                    Add to trip
                </button>
            </li>
        </ul>

        <p v-else-if="!loading && !loadError" class="vyamap-help">
            {{ searching ? 'No flights without a trip match this search.' : 'No flights without a trip on these dates.' }}
        </p>
    </div>
</template>
