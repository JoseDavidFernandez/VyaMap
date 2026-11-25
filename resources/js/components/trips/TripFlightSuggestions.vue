<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import { flightDepartureLabel } from '../../utils/flightTime';
import { isOutsideDates, tripDays } from '../../utils/tripDates';
import { flightRouteLabel, flightSuggestionsUrl, loadStandaloneFlights } from '../../utils/standaloneFlights';
import type { FlightRecord } from '../../types/flights';

/*
 * Sugerencias de vuelos independientes al crear o editar un viaje: los del
 * usuario cuya fecha local de salida cae entre el inicio y el fin, con un día
 * de margen por cada lado (lo filtra el servidor).
 *
 * - Aceptar marca el vuelo para asociarlo al guardar el viaje (v-model con
 *   los ids aceptados). Descartar solo lo oculta: no se guarda nada y puede
 *   volver a salir la próxima vez.
 * - Un vuelo del margen (fuera de las fechas) se avisa, no se bloquea.
 */

const props = defineProps<{
    startDate: string;
    endDate: string;
}>();

const accepted = defineModel<number[]>({ default: () => [] });

const suggestions = ref<FlightRecord[]>([]);
const dismissed = ref<number[]>([]);
const loading = ref(false);
const loadError = ref('');

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const validRange = computed(() => tripDays(props.startDate, props.endDate) !== null);

const load = () => {
    if (timer) {
        clearTimeout(timer);
    }

    if (!validRange.value) {
        suggestions.value = [];
        accepted.value = [];

        return;
    }

    timer = setTimeout(async () => {
        const current = ++requestId;
        loading.value = true;
        loadError.value = '';

        try {
            const flights = await loadStandaloneFlights(flightSuggestionsUrl(props.startDate, props.endDate));

            if (current === requestId) {
                suggestions.value = flights;
                // Un vuelo aceptado que ya no cae en las nuevas fechas deja de estarlo.
                const ids = flights.map((flight) => flight.id);
                accepted.value = accepted.value.filter((id) => ids.includes(id));
            }
        } catch {
            if (current === requestId) {
                suggestions.value = [];
                loadError.value = 'Flight suggestions are not available right now.';
            }
        } finally {
            if (current === requestId) {
                loading.value = false;
            }
        }
    }, 250);
};

watch(() => [props.startDate, props.endDate], load, { immediate: true });

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const visible = computed(() => suggestions.value.filter((flight) => !dismissed.value.includes(flight.id)));
const pending = computed(() => visible.value.filter((flight) => !accepted.value.includes(flight.id)));

const isAccepted = (flight: FlightRecord) => accepted.value.includes(flight.id);
const isOutside = (flight: FlightRecord) => isOutsideDates(flight.departure_date, props.startDate, props.endDate);

const accept = (flight: FlightRecord) => {
    if (!isAccepted(flight)) {
        accepted.value = [...accepted.value, flight.id];
    }
};

const undo = (flight: FlightRecord) => {
    accepted.value = accepted.value.filter((id) => id !== flight.id);
};

const dismiss = (flight: FlightRecord) => {
    undo(flight);
    dismissed.value = [...dismissed.value, flight.id];
};

const acceptAll = () => {
    accepted.value = [...new Set([...accepted.value, ...pending.value.map((flight) => flight.id)])];
};

const dismissAll = () => {
    const ids = pending.value.map((flight) => flight.id);
    dismissed.value = [...dismissed.value, ...ids];
};
</script>

<template>
    <section v-if="visible.length || loadError" class="mt-6 border-t border-[var(--vyamap-border)] pt-5" data-flight-suggestions>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="vyamap-h3">Flights without a trip</h2>
                <p class="vyamap-help !mt-0.5">Flights on these dates, one day either side. Accept them to add them to this trip.</p>
            </div>
            <div v-if="pending.length > 1" class="flex gap-1">
                <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="acceptAll">Accept all</button>
                <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="dismissAll">Dismiss all</button>
            </div>
        </div>

        <p v-if="loadError" class="vyamap-error">{{ loadError }}</p>

        <ul class="flex flex-col gap-2">
            <li
                v-for="flight in visible"
                :key="flight.id"
                class="vyamap-flight flex flex-wrap items-center justify-between gap-3"
                :data-suggestion="flight.id"
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

                <div class="flex shrink-0 gap-1">
                    <template v-if="isAccepted(flight)">
                        <span class="vyamap-badge vyamap-badge-cyan">
                            <Icon name="check" :size="14" />
                            Will be added
                        </span>
                        <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="undo(flight)">Undo</button>
                    </template>
                    <template v-else>
                        <button type="button" class="vyamap-button-secondary vyamap-button-sm" @click="accept(flight)">Accept</button>
                        <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="dismiss(flight)">Dismiss</button>
                    </template>
                </div>
            </li>
        </ul>

        <p v-if="loading" class="vyamap-help">Looking for flights…</p>
    </section>
</template>
