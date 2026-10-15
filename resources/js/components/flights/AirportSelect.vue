<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import type { AirportSearchResult } from '../../types/flights';

/*
 * Selector de aeropuerto sobre /airports/search: busca por IATA, ICAO, nombre
 * o ciudad y muestra resultados inequívocos (código, nombre y ciudad, país).
 */

const props = defineProps<{
    id: string;
    label: string;
    // Texto mostrado del aeropuerto elegido (v-model:display).
    display: string;
    error?: string;
}>();

const model = defineModel<number | null>({ required: true });

const emit = defineEmits<{
    'update:display': [value: string];
}>();

const query = ref(props.display);
const results = ref<AirportSearchResult[]>([]);
const searching = ref(false);
const searchError = ref('');

let timer: ReturnType<typeof setTimeout> | null = null;
let requestId = 0;

const describe = (airport: AirportSearchResult) => {
    const code = airport.iata_code || airport.icao_code || '—';
    const place = [airport.city, airport.country].filter(Boolean).join(', ');

    return place ? `${code} · ${airport.name} — ${place}` : `${code} · ${airport.name}`;
};

const search = () => {
    searchError.value = '';

    // Escribir de nuevo invalida la selección anterior.
    if (model.value !== null) {
        model.value = null;
        emit('update:display', '');
    }

    if (timer) {
        clearTimeout(timer);
    }

    if (query.value.trim().length < 2) {
        results.value = [];

        return;
    }

    timer = setTimeout(async () => {
        const current = ++requestId;
        searching.value = true;

        try {
            const response = await fetch(`/airports/search?q=${encodeURIComponent(query.value.trim())}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (current === requestId) {
                results.value = data.results ?? [];
            }
        } catch {
            if (current === requestId) {
                results.value = [];
                searchError.value = 'Airport search is not available right now.';
            }
        } finally {
            if (current === requestId) {
                searching.value = false;
            }
        }
    }, 250);
};

const select = (airport: AirportSearchResult) => {
    model.value = airport.id;
    query.value = describe(airport);
    emit('update:display', query.value);
    results.value = [];
};

// El formulario puede cambiar el aeropuerto desde fuera (invertir, vuelta, conexión).
const sync = (display: string) => {
    query.value = display;
    results.value = [];
};

defineExpose({ sync });

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <div class="relative">
        <label :for="id" class="vyamap-label">{{ label }}</label>

        <input
            :id="id"
            v-model="query"
            type="text"
            class="vyamap-input"
            autocomplete="off"
            placeholder="MAD, Barajas, Madrid…"
            :aria-invalid="Boolean(error)"
            @input="search"
        />

        <ul
            v-if="results.length"
            class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-white/10 bg-[#0d1119] py-1 shadow-2xl"
            role="listbox"
        >
            <li v-for="airport in results" :key="airport.id">
                <button
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm text-white/80 transition hover:bg-white/[0.06] hover:text-white"
                    @click="select(airport)"
                >
                    {{ describe(airport) }}
                </button>
            </li>
        </ul>

        <p v-if="searching" class="mt-1 text-[11px] text-white/40">Searching…</p>
        <p v-else-if="searchError" class="vyamap-error">{{ searchError }}</p>
        <p v-else-if="query.trim().length >= 2 && model === null && !results.length" class="mt-1 text-[11px] text-white/40">
            Choose an airport from the list.
        </p>
        <p v-if="error" class="vyamap-error">{{ error }}</p>
    </div>
</template>
