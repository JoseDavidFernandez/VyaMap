<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../ui/Icon.vue';
import { flightDepartureLabel } from '../../utils/flightTime';
import { isOutsideDates, tripDays } from '../../utils/tripDates';
import {
    flightRouteLabel,
    flightSuggestionsUrl,
    loadStandaloneFlights,
    lostByDatesMessage,
    unavailableFlightsMessage,
} from '../../utils/standaloneFlights';
import type { FlightRecord } from '../../types/flights';

/*
 * Sugerencias de vuelos independientes al crear o editar un viaje: los del
 * usuario cuya fecha local de salida cae entre el inicio y el fin, con un día
 * de margen por cada lado (lo filtra el servidor).
 *
 * - Aceptar marca el vuelo para asociarlo al guardar el viaje (v-model con
 *   los ids aceptados). Descartar lo deja en la lista atenuado, con «Undo»;
 *   no se guarda nada y puede volver a salir la próxima vez.
 * - Si al cambiar las fechas un vuelo aceptado queda fuera del margen, deja
 *   de estar aceptado y se avisa. Durante la sesión del formulario se
 *   recuerda: si las fechas vuelven a incluirlo, vuelve aceptado.
 * - markUnavailable(): el servidor rechazó vuelos que otra petición ya ha
 *   asociado a otro viaje. Se quitan, se recarga la lista y se avisa.
 * - Un vuelo del margen (fuera de las fechas) se avisa, no se bloquea.
 */

const props = defineProps<{
    startDate: string;
    endDate: string;
}>();

const accepted = defineModel<number[]>({ default: () => [] });

const suggestions = ref<FlightRecord[]>([]);
const dismissed = ref<number[]>([]);
// Aceptados durante la sesión del formulario, aunque las fechas los dejen fuera.
const remembered = ref<number[]>([...accepted.value]);
const loading = ref(false);
const loadError = ref('');
const datesNotice = ref('');
const unavailableNotice = ref('');

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const validRange = computed(() => tripDays(props.startDate, props.endDate) !== null);

const fetchSuggestions = async () => {
    const current = ++requestId;
    loading.value = true;
    loadError.value = '';

    try {
        const flights = await loadStandaloneFlights(flightSuggestionsUrl(props.startDate, props.endDate));

        if (current !== requestId) {
            return;
        }

        const ids = flights.map((flight) => flight.id);
        const lost = accepted.value.filter((id) => !ids.includes(id));

        suggestions.value = flights;
        accepted.value = remembered.value.filter((id) => ids.includes(id));
        datesNotice.value = lostByDatesMessage(lost.length);
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
};

const load = () => {
    if (timer) {
        clearTimeout(timer);
    }

    // Fechas incompletas o al revés (mientras se escriben): no se pierde nada.
    if (!validRange.value) {
        suggestions.value = [];

        return;
    }

    timer = setTimeout(fetchSuggestions, 250);
};

watch(() => [props.startDate, props.endDate], load, { immediate: true });

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const pending = computed(() =>
    suggestions.value.filter((flight) => !accepted.value.includes(flight.id) && !dismissed.value.includes(flight.id)),
);

const isAccepted = (flight: FlightRecord) => accepted.value.includes(flight.id);
const isDismissed = (flight: FlightRecord) => dismissed.value.includes(flight.id);
const isOutside = (flight: FlightRecord) => isOutsideDates(flight.departure_date, props.startDate, props.endDate);

const accept = (flight: FlightRecord) => {
    if (!isAccepted(flight)) {
        accepted.value = [...accepted.value, flight.id];
        remembered.value = [...new Set([...remembered.value, flight.id])];
    }
};

const undoAccept = (flight: FlightRecord) => {
    accepted.value = accepted.value.filter((id) => id !== flight.id);
    remembered.value = remembered.value.filter((id) => id !== flight.id);
};

const dismiss = (flight: FlightRecord) => {
    dismissed.value = [...dismissed.value, flight.id];
};

const undoDismiss = (flight: FlightRecord) => {
    dismissed.value = dismissed.value.filter((id) => id !== flight.id);
};

// De una vez: el v-model solo se actualiza cuando el padre recibe el valor.
const acceptAll = () => {
    const ids = pending.value.map((flight) => flight.id);

    accepted.value = [...new Set([...accepted.value, ...ids])];
    remembered.value = [...new Set([...remembered.value, ...ids])];
};

const dismissAll = () => {
    dismissed.value = [...dismissed.value, ...pending.value.map((flight) => flight.id)];
};

const markUnavailable = async (ids: number[]) => {
    if (!ids.length) {
        return;
    }

    accepted.value = accepted.value.filter((id) => !ids.includes(id));
    remembered.value = remembered.value.filter((id) => !ids.includes(id));
    suggestions.value = suggestions.value.filter((flight) => !ids.includes(flight.id));
    unavailableNotice.value = unavailableFlightsMessage(ids.length);

    if (validRange.value) {
        await fetchSuggestions();
    }
};

defineExpose({ markUnavailable });
</script>

<template>
    <section
        v-if="suggestions.length || loadError || datesNotice || unavailableNotice"
        class="mt-6 border-t border-[var(--vyamap-border)] pt-5"
        data-flight-suggestions
    >
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
        <p v-if="unavailableNotice" class="mb-2 text-[0.8125rem] text-[var(--vyamap-warning)]" role="alert" data-unavailable-notice>
            {{ unavailableNotice }}
        </p>
        <p v-if="datesNotice" class="mb-2 text-[0.8125rem] text-[var(--vyamap-warning)]" role="status" data-dates-notice>
            {{ datesNotice }}
        </p>

        <ul class="flex flex-col gap-2">
            <li
                v-for="flight in suggestions"
                :key="flight.id"
                class="vyamap-flight flex flex-wrap items-center justify-between gap-3"
                :class="{ 'opacity-55': isDismissed(flight) }"
                :data-suggestion="flight.id"
                :data-dismissed="isDismissed(flight) ? 'true' : undefined"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ flightRouteLabel(flight) }}</p>
                    <p class="text-[0.8125rem] text-[var(--vyamap-text-muted)]">
                        <span class="vyamap-number">{{ flightDepartureLabel(flight) }}</span>
                        <span v-if="flight.origin.city && flight.destination.city"> · {{ flight.origin.city }} → {{ flight.destination.city }}</span>
                    </p>
                    <p v-if="isOutside(flight) && !isDismissed(flight)" class="text-[0.8125rem] text-[var(--vyamap-warning)]" data-outside-warning>
                        Outside the trip dates. It can still be added.
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <template v-if="isAccepted(flight)">
                        <span class="vyamap-badge vyamap-badge-cyan">
                            <Icon name="check" :size="14" />
                            Will be added
                        </span>
                        <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="undoAccept(flight)">Undo</button>
                    </template>
                    <template v-else-if="isDismissed(flight)">
                        <span class="vyamap-badge">Dismissed</span>
                        <button type="button" class="vyamap-button-ghost vyamap-button-sm" @click="undoDismiss(flight)">Undo</button>
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
