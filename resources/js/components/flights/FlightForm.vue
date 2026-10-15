<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AirportSelect from './AirportSelect.vue';
import { flightTime } from '../../utils/flightTime';
import type { FlightAirport, FlightRecord, TripOption } from '../../types/flights';

/*
 * Formulario único de vuelo (Flight History y ficha del viaje).
 *
 * Obligatorios: origen, destino y fecha local de salida. Opcionales: horas,
 * número de vuelo, aerolínea y viaje. Nunca se rellenan horas ni números:
 * "vuelta" y "conexión" solo reutilizan aeropuertos, viaje y fechas.
 *
 * - tripId: ficha del viaje (rutas /trips/{trip}/flights; el viaje es fijo).
 * - trips: Flight History (rutas /flights; viaje opcional).
 */

const props = defineProps<{
    flight?: FlightRecord | null;
    tripId?: number | null;
    trips?: TripOption[];
    defaultDate?: string | null;
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

// La hora de llegada solo tiene sentido con hora de salida (D4).
const clearArrivalIfNeeded = () => {
    if (!form.departure_time) {
        form.arrival_time = '';
        form.arrival_offset = 0;
    }
};

const submit = (action: NextAction) => {
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
            departure_time: data.departure_time || null,
            arrival_time: data.departure_time && data.arrival_time ? data.arrival_time : null,
            arrival_date: data.departure_time && data.arrival_time && data.departure_date
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
        arrivalDate: form.departure_time && form.arrival_time ? addDays(form.departure_date, Number(form.arrival_offset)) : null,
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

// arrival_date se calcula al enviar (no es un campo del formulario), pero el servidor puede rechazarla.
const arrivalDateError = computed(() => (form.errors as Record<string, string | undefined>).arrival_date);
</script>

<template>
    <form class="space-y-5" @submit.prevent="submit('close')">
        <p v-if="notice" class="rounded-xl border border-cyan-300/20 bg-cyan-300/[0.06] px-4 py-2 text-sm text-cyan-100">
            {{ notice }}
        </p>

        <div class="grid gap-4 sm:grid-cols-[1fr_auto_1fr] sm:items-start">
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
                class="mx-auto mt-0 flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-white/60 transition hover:bg-white/[0.06] hover:text-white sm:mt-7"
                title="Swap origin and destination"
                aria-label="Swap origin and destination"
                @click="swapAirports"
            >
                ⇄
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

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="flight-date" class="vyamap-label">Date</label>
                <input id="flight-date" v-model="form.departure_date" type="date" class="vyamap-input" required />
                <p class="mt-1 text-[11px] text-white/40">Local date at the origin airport.</p>
                <p v-if="form.errors.departure_date" class="vyamap-error">{{ form.errors.departure_date }}</p>
            </div>

            <div>
                <label for="flight-departure-time" class="vyamap-label">Departure time <span class="text-white/35">(optional)</span></label>
                <input id="flight-departure-time" v-model="form.departure_time" type="time" class="vyamap-input" @change="clearArrivalIfNeeded" />
                <p v-if="form.errors.departure_time" class="vyamap-error">{{ form.errors.departure_time }}</p>
            </div>

            <div>
                <label for="flight-arrival-time" class="vyamap-label">Arrival time <span class="text-white/35">(optional)</span></label>
                <div class="flex gap-2">
                    <input
                        id="flight-arrival-time"
                        v-model="form.arrival_time"
                        type="time"
                        class="vyamap-input"
                        :disabled="!form.departure_time"
                        :title="form.departure_time ? '' : 'Add the departure time first'"
                    />
                    <select
                        v-model="form.arrival_offset"
                        class="vyamap-input w-28"
                        :disabled="!form.arrival_time"
                        aria-label="Arrival day"
                    >
                        <option :value="-1">−1 day</option>
                        <option :value="0">Same day</option>
                        <option :value="1">+1 day</option>
                        <option :value="2">+2 days</option>
                    </select>
                </div>
                <p v-if="form.errors.arrival_time" class="vyamap-error">{{ form.errors.arrival_time }}</p>
                <p v-if="arrivalDateError" class="vyamap-error">{{ arrivalDateError }}</p>
            </div>
        </div>

        <div class="grid gap-4" :class="tripId ? 'sm:grid-cols-2' : 'sm:grid-cols-3'">
            <div>
                <label for="flight-number" class="vyamap-label">Flight number <span class="text-white/35">(optional)</span></label>
                <input id="flight-number" v-model="form.flight_number" type="text" maxlength="20" class="vyamap-input uppercase" placeholder="IB3456" />
                <p v-if="form.errors.flight_number" class="vyamap-error">{{ form.errors.flight_number }}</p>
            </div>

            <div>
                <label for="flight-airline" class="vyamap-label">Airline <span class="text-white/35">(optional)</span></label>
                <input id="flight-airline" v-model="form.airline" type="text" maxlength="100" class="vyamap-input" placeholder="Iberia" />
                <p v-if="form.errors.airline" class="vyamap-error">{{ form.errors.airline }}</p>
            </div>

            <div v-if="!tripId">
                <label for="flight-trip" class="vyamap-label">Trip <span class="text-white/35">(optional)</span></label>
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
