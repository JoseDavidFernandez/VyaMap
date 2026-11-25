<script setup lang="ts">
import Icon from '../ui/Icon.vue';
import { computed, reactive, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AirportSelect from './AirportSelect.vue';
import { flightTime, normalizeTimeInput } from '../../utils/flightTime';
import { isOutsideDates } from '../../utils/tripDates';
import type { FlightAirport, FlightRecord, TripOption } from '../../types/flights';

/*
 * Formulario único de vuelo (Flight History y ficha del viaje).
 *
 * Obligatorios: origen, destino y fecha local de salida. Opcionales: horas,
 * número de vuelo, aerolínea y viaje. Nunca se rellenan horas ni números:
 * "vuelta" y "conexión" solo reutilizan aeropuertos, viaje y fechas.
 *
 * - tripId: ficha del viaje (rutas /trips/{trip}/flights; el viaje es fijo),
 *   con tripDates para avisar de una salida fuera de sus fechas.
 * - trips: Flight History (rutas /flights; viaje opcional).
 *
 * Una salida fuera de las fechas del viaje se avisa, no se bloquea (D3).
 */

const props = defineProps<{
    flight?: FlightRecord | null;
    tripId?: number | null;
    trips?: TripOption[];
    defaultDate?: string | null;
    tripDates?: { start_date: string | null; end_date: string | null } | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

type NextAction = 'close' | 'return' | 'connection';

const airportLabel = (airport: FlightAirport) =>
    [airport.code, airport.airport].filter(Boolean).join(' · ') + (airport.city ? ` — ${airport.city}` : '');

// Días entre la fecha local de salida y la de llegada (para "+1 day").
const arrivalOffsetOf = (flight: FlightRecord): number => {
    if (!flight.arrival || !flight.destination.timezone) {
        return 0;
    }

    const local = new Intl.DateTimeFormat('en-CA', { timeZone: flight.destination.timezone, year: 'numeric', month: '2-digit', day: '2-digit' })
        .format(new Date(flight.arrival));

    return Math.round((Date.parse(`${local}T00:00:00Z`) - Date.parse(`${flight.departure_date}T00:00:00Z`)) / 86_400_000);
};

const editing = computed(() => Boolean(props.flight));

const form = useForm({
    origin_airport_id: props.flight?.origin.id ?? null as number | null,
    destination_airport_id: props.flight?.destination.id ?? null as number | null,
    departure_date: props.flight?.departure_date ?? props.defaultDate ?? '',
    departure_time: props.flight?.departure ? flightTime(props.flight.departure, props.flight.origin.timezone) : '',
    arrival_time: props.flight?.arrival ? flightTime(props.flight.arrival, props.flight.destination.timezone) : '',
    arrival_offset: props.flight ? arrivalOffsetOf(props.flight) : 0,
    flight_number: props.flight?.flight_number ?? '',
    airline: props.flight?.airline ?? '',
    trip_id: props.flight?.trip?.id ?? null as number | null,
});

const originDisplay = ref(props.flight ? airportLabel(props.flight.origin) : '');
const destinationDisplay = ref(props.flight ? airportLabel(props.flight.destination) : '');
const originSelect = ref<InstanceType<typeof AirportSelect> | null>(null);
const destinationSelect = ref<InstanceType<typeof AirportSelect> | null>(null);
const nextAction = ref<NextAction>('close');
const notice = ref('');

const addDays = (date: string, days: number) => {
    const value = new Date(`${date}T00:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);

    return value.toISOString().slice(0, 10);
};

const swapAirports = () => {
    [form.origin_airport_id, form.destination_airport_id] = [form.destination_airport_id, form.origin_airport_id];
    [originDisplay.value, destinationDisplay.value] = [destinationDisplay.value, originDisplay.value];
    originSelect.value?.sync(originDisplay.value);
    destinationSelect.value?.sync(destinationDisplay.value);
};

/*
 * Horas de salida y llegada: campos de texto en formato de 24 horas (HH:MM).
 *
 * No se usa <input type="time">: cada navegador lo implementa a su manera
 * (segmentos, eventos que no siempre llegan, value vacío con la hora visible
 * y validity.badInput cambiante en Safari), y el formulario no puede saber de
 * forma fiable qué hora ve el usuario. Un campo de texto vale siempre lo que
 * se ha escrito y emite "input" en cada pulsación en todos los navegadores,
 * así que v-model nunca deshace lo que se está tecleando.
 *
 * - Mientras se escribe no se valida; al salir del campo la hora se normaliza
 *   ("7:30" o "0730" → "07:30") o se avisa si está incompleta o no es válida.
 * - Vacía es válida (las horas son opcionales).
 * - Una hora inválida bloquea el guardado; al servidor solo llega HH:MM.
 * - Cambiar o borrar la salida nunca modifica la llegada ni su día.
 */
type TimeField = 'departure_time' | 'arrival_time';

const invalidTimes = reactive<Record<TimeField, boolean>>({ departure_time: false, arrival_time: false });

const invalidTimeMessage = 'Use the 24-hour format HH:MM, for example 07:30, or leave it empty.';

// Al salir del campo: normaliza o marca el error.
const checkTime = (field: TimeField) => {
    const normalized = normalizeTimeInput(form[field]);

    invalidTimes[field] = normalized === null;

    if (normalized !== null && normalized !== form[field]) {
        form[field] = normalized;
    }
};

// Al escribir solo se retira el aviso, nunca se muestra (no se interrumpe a mitad de hora).
const clearTimeError = (field: TimeField) => {
    if (invalidTimes[field] && normalizeTimeInput(form[field]) !== null) {
        invalidTimes[field] = false;
    }
};

// La llegada sin salida no se descarta: el servidor la rechaza (D4) y aquí se avisa antes.
const arrivalNeedsDeparture = computed(() => Boolean(form.arrival_time.trim()) && !form.departure_time.trim());

const submit = (action: NextAction) => {
    checkTime('departure_time');
    checkTime('arrival_time');

    if (invalidTimes.departure_time || invalidTimes.arrival_time) {
        return;
    }

    nextAction.value = action;
    notice.value = '';

    const fixedTrip = props.tripId ?? null;
    const url = fixedTrip
        ? (props.flight ? `/trips/${fixedTrip}/flights/${props.flight.id}` : `/trips/${fixedTrip}/flights`)
        : (props.flight ? `/flights/${props.flight.id}` : '/flights');

    form
        .transform((data) => ({
            origin_airport_id: data.origin_airport_id,
            destination_airport_id: data.destination_airport_id,
            departure_date: data.departure_date,
            // Ya normalizadas por checkTime() al guardar.
            departure_time: data.departure_time || null,
            arrival_time: data.arrival_time || null,
            arrival_date: data.arrival_time && data.departure_date
                ? addDays(data.departure_date, Number(data.arrival_offset))
                : null,
            flight_number: data.flight_number || null,
            airline: data.airline || null,
            ...(fixedTrip ? {} : { trip_id: data.trip_id }),
        }))
        [props.flight ? 'put' : 'post'](url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => afterSave(),
        });
};

const afterSave = () => {
    if (nextAction.value === 'close') {
        emit('close');

        return;
    }

    const previous = {
        origin: form.origin_airport_id,
        destination: form.destination_airport_id,
        originDisplay: originDisplay.value,
        destinationDisplay: destinationDisplay.value,
        date: form.departure_date,
        arrivalDate: form.arrival_time ? addDays(form.departure_date, Number(form.arrival_offset)) : null,
        trip: form.trip_id,
    };

    form.reset();
    form.clearErrors();
    form.trip_id = previous.trip;

    if (nextAction.value === 'return') {
        // Vuelta: aeropuertos invertidos; la fecha la pone el usuario.
        form.origin_airport_id = previous.destination;
        form.destination_airport_id = previous.origin;
        originDisplay.value = previous.destinationDisplay;
        destinationDisplay.value = previous.originDisplay;
        form.departure_date = '';
        notice.value = 'Saved. Add the return flight.';
    } else {
        // Conexión: sale de donde llegó el anterior, el mismo día (o el de llegada).
        form.origin_airport_id = previous.destination;
        form.destination_airport_id = null;
        originDisplay.value = previous.destinationDisplay;
        destinationDisplay.value = '';
        form.departure_date = previous.arrivalDate ?? previous.date;
        notice.value = 'Saved. Add the connecting flight.';
    }

    originSelect.value?.sync(originDisplay.value);
    destinationSelect.value?.sync(destinationDisplay.value);
};

const selectableTrips = computed(() => props.trips ?? []);

// Viaje elegido (fijo o del selector) y aviso si la salida cae fuera de sus fechas.
const chosenTripDates = computed(() =>
    props.tripId
        ? props.tripDates ?? null
        : selectableTrips.value.find((trip) => trip.id === form.trip_id) ?? null,
);

const outsideTripDates = computed(() =>
    chosenTripDates.value !== null
    && isOutsideDates(form.departure_date, chosenTripDates.value.start_date, chosenTripDates.value.end_date),
);

// arrival_date se calcula al enviar (no es un campo del formulario), pero el servidor puede rechazarla.
const arrivalDateError = computed(() => (form.errors as Record<string, string | undefined>).arrival_date);
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="submit('close')">
        <p v-if="notice" role="status" class="rounded-[var(--vyamap-radius-md)] border border-[rgba(34,211,238,0.35)] bg-[rgba(34,211,238,0.08)] px-4 py-2.5 text-sm text-[var(--vyamap-text)]">
            {{ notice }}
        </p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-start">
            <AirportSelect
                id="flight-origin"
                ref="originSelect"
                v-model="form.origin_airport_id"
                v-model:display="originDisplay"
                label="From"
                :error="form.errors.origin_airport_id"
            />

            <button
                type="button"
                class="vyamap-icon-button mx-auto !h-10 !w-10 sm:mt-7"
                title="Swap origin and destination"
                aria-label="Swap origin and destination"
                @click="swapAirports"
            >
                <Icon name="swap" :size="18" />
            </button>

            <AirportSelect
                id="flight-destination"
                ref="destinationSelect"
                v-model="form.destination_airport_id"
                v-model:display="destinationDisplay"
                label="To"
                :error="form.errors.destination_airport_id"
            />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label for="flight-date" class="vyamap-label">Date</label>
                <input id="flight-date" v-model="form.departure_date" type="date" class="vyamap-input" required />
                <p class="vyamap-help">Local date at the origin airport.</p>
                <p v-if="form.errors.departure_date" class="vyamap-error">{{ form.errors.departure_date }}</p>
                <p v-else-if="outsideTripDates" class="mt-1.5 text-[0.8125rem] text-[var(--vyamap-warning)]" data-outside-warning>
                    Outside the trip dates. You can still save it.
                </p>
            </div>

            <div>
                <label for="flight-departure-time" class="vyamap-label">Departure time <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                <input
                    id="flight-departure-time"
                    v-model="form.departure_time"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="5"
                    placeholder="HH:MM"
                    class="vyamap-input tabular-nums"
                    :aria-invalid="invalidTimes.departure_time"
                    @input="clearTimeError('departure_time')"
                    @blur="checkTime('departure_time')"
                />
                <p class="vyamap-help">24-hour local time at the origin airport.</p>
                <p v-if="invalidTimes.departure_time" class="vyamap-error">{{ invalidTimeMessage }}</p>
                <p v-else-if="form.errors.departure_time" class="vyamap-error">{{ form.errors.departure_time }}</p>
            </div>

            <div class="@container">
                <label for="flight-arrival-time" class="vyamap-label">Arrival time <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                <!-- Hora y día juntos solo si la columna tiene sitio para ambos; si no, uno bajo el otro. -->
                <div class="grid grid-cols-1 gap-2 @2xs:grid-cols-[minmax(0,1fr)_8.5rem]">
                    <input
                        id="flight-arrival-time"
                        v-model="form.arrival_time"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="5"
                        placeholder="HH:MM"
                        class="vyamap-input tabular-nums"
                        :aria-invalid="invalidTimes.arrival_time"
                        @input="clearTimeError('arrival_time')"
                        @blur="checkTime('arrival_time')"
                    />
                    <select
                        id="flight-arrival-day"
                        v-model="form.arrival_offset"
                        class="vyamap-input !pl-3 !pr-8"
                        aria-label="Arrival day"
                    >
                        <option :value="-1">−1 day</option>
                        <option :value="0">Same day</option>
                        <option :value="1">+1 day</option>
                        <option :value="2">+2 days</option>
                    </select>
                </div>
                <p class="vyamap-help">24-hour local time at the destination airport.</p>
                <p v-if="invalidTimes.arrival_time" class="vyamap-error">{{ invalidTimeMessage }}</p>
                <p v-else-if="form.errors.arrival_time" class="vyamap-error">{{ form.errors.arrival_time }}</p>
                <p v-else-if="arrivalNeedsDeparture" class="mt-1.5 text-[0.8125rem] text-[var(--vyamap-warning)]">Add the departure time too: an arrival time needs one.</p>
                <p v-if="arrivalDateError" class="vyamap-error">{{ arrivalDateError }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4" :class="tripId ? 'sm:grid-cols-2' : 'sm:grid-cols-3'">
            <div>
                <label for="flight-number" class="vyamap-label">Flight number <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                <input id="flight-number" v-model="form.flight_number" type="text" maxlength="20" class="vyamap-input uppercase" placeholder="IB3456" />
                <p v-if="form.errors.flight_number" class="vyamap-error">{{ form.errors.flight_number }}</p>
            </div>

            <div>
                <label for="flight-airline" class="vyamap-label">Airline <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                <input id="flight-airline" v-model="form.airline" type="text" maxlength="100" class="vyamap-input" placeholder="Iberia" />
                <p v-if="form.errors.airline" class="vyamap-error">{{ form.errors.airline }}</p>
            </div>

            <div v-if="!tripId">
                <label for="flight-trip" class="vyamap-label">Trip <span class="text-[var(--vyamap-text-subtle)]">(optional)</span></label>
                <select id="flight-trip" v-model="form.trip_id" class="vyamap-input">
                    <option :value="null">No trip</option>
                    <option v-for="trip in selectableTrips" :key="trip.id" :value="trip.id">
                        {{ trip.name }}{{ trip.start_date ? ` · ${trip.start_date}` : '' }}
                    </option>
                </select>
                <p v-if="form.errors.trip_id" class="vyamap-error">{{ form.errors.trip_id }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
            <button type="submit" class="vyamap-button" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : editing ? 'Save changes' : 'Save' }}
            </button>

            <template v-if="!editing">
                <button type="button" class="vyamap-button-secondary" :disabled="form.processing" @click="submit('return')">
                    Save &amp; add return
                </button>
                <button type="button" class="vyamap-button-secondary" :disabled="form.processing" @click="submit('connection')">
                    Save &amp; add connection
                </button>
            </template>

            <button type="button" class="vyamap-button-secondary" :disabled="form.processing" @click="emit('close')">
                {{ notice ? 'Done' : 'Cancel' }}
            </button>
        </div>
    </form>
</template>
