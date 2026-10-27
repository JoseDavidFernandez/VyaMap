<script setup lang="ts">

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import { escapeHtml } from '../../utils/escapeHtml';

interface City {
    id: number;
    name: string;
    country: string;
    iso_code: string;
    latitude: number | null;
    longitude: number | null;
}

interface Visit {
    id: number;
    city: City;
    visited_from: string | null;
    visited_until: string | null;
    notes: string | null;
}

const props = withDefaults(defineProps<{
    visits: Visit[];
    // Fase del viaje: violeta para planificados, en curso o pendientes; gris si está cancelado.
    phase?: 'upcoming' | 'ongoing' | 'pending' | 'completed' | 'cancelled';
}>(), {
    phase: 'completed',
});

const overlayKicker = computed(() => ({
    completed: 'Places visited',
    upcoming: 'Planned destinations',
    ongoing: 'Trip in progress',
    pending: 'Pending confirmation',
    cancelled: 'Cancelled trip',
})[props.phase]);

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;

const formatVisitDate = (date: string | null) => {
    if (!date) {
        return null;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};

const renderMap = async () => {
    if (!map) {
        return;
    }

    await nextTick();

    if (markerLayer) {
        markerLayer.clearLayers();
    } else {
        markerLayer = L.layerGroup().addTo(map);
    }

    const bounds: L.LatLngExpression[] = [];

    props.visits.forEach((visit) => {
        const latitude = Number(visit.city.latitude);
        const longitude = Number(visit.city.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        const position: [number, number] = [latitude, longitude];
        bounds.push(position);

        const from = formatVisitDate(visit.visited_from);
        const until = formatVisitDate(visit.visited_until);
        const dateLine = from && until && from !== until
            ? `${from} — ${until}`
            : from ?? until ?? '';

        const icon = L.divIcon({
            className: 'vyamap-visited-icon',
            html: `
                <div class="vyamap-visited-marker">
                    <div class="vyamap-visited-pin"><span class="vyamap-visited-dot"></span></div>
                    <div class="vyamap-visited-label">
                        <span>${escapeHtml(visit.city.name)}</span>
                        <small>${escapeHtml(dateLine)}</small>
                    </div>
                </div>
            `,
            iconSize: [1, 1],
            iconAnchor: [15, 36],
        });

        const marker = L.marker(position, {
            icon,
            keyboard: false,
        });

        marker.bindTooltip(
            `<strong>${escapeHtml(visit.city.name)}</strong><br>${escapeHtml(visit.city.country)}`,
            {
                direction: 'top',
                offset: [0, -34],
                className: 'vyamap-visited-tooltip',
            },
        );

        marker.addTo(markerLayer!);
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 8);
    } else if (bounds.length > 1) {
        map.fitBounds(L.latLngBounds(bounds), {
            padding: [70, 70],
            maxZoom: 7,
        });
    } else {
        map.setView([40.4, -3.7], 4);
    }

    setTimeout(() => map?.invalidateSize(), 0);
};

onMounted(async () => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        preferCanvas: true,
        worldCopyJump: true,
    });

    L.control.zoom({
        position: 'bottomright',
    }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);

    await renderMap();
});

watch(
    () => props.visits,
    () => {
        renderMap();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }

    markerLayer = null;
});

</script>

<template>
    <div
        ref="mapElement"
        class="visited-map vyamap-leaflet-dark"
        :class="{
            'visited-map--planned': phase === 'upcoming' || phase === 'ongoing' || phase === 'pending',
            'visited-map--ongoing': phase === 'ongoing',
            'visited-map--cancelled': phase === 'cancelled',
        }"
    >
        <div class="visited-map-overlay">
            <div class="visited-map-kicker">{{ overlayKicker }}</div>
            <div class="visited-map-count">{{ visits.length }} {{ visits.length === 1 ? 'city' : 'cities' }}</div>
        </div>
    </div>
</template>

<style scoped>
.visited-map {
    position: relative;
    height: 100%;
    min-height: 100%;
    width: 100%;
    overflow: hidden;
    background: #0A0F1B;
}

.visited-map :deep(.vyamap-visited-icon) {
    width: 1px !important;
    height: 1px !important;
    border: 0 !important;
    background: transparent !important;
}

.visited-map :deep(.vyamap-visited-marker) {
    position: relative;
    display: flex;
    align-items: center;
    gap: 7px;
    width: max-content;
    transform: translate(-1px, -36px);
}

.visited-map :deep(.vyamap-visited-pin) {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 999px;
    background: rgba(10, 16, 30, 0.92);
    border: 1px solid rgba(34, 211, 238, 0.55);
    box-shadow:
        0 0 0 4px rgba(103, 232, 249, 0.08),
        0 8px 20px rgba(0, 0, 0, 0.28);
}

/* Punto de la ciudad: acento cian (cyan-300) de la paleta de VyaMap. */
.visited-map :deep(.vyamap-visited-dot) {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #22D3EE;
    box-shadow: 0 0 10px rgba(103, 232, 249, 0.65);
}

.visited-map :deep(.vyamap-visited-label) {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 0.375rem 0.625rem;
    border: 1px solid var(--vyamap-border-strong);
    border-radius: var(--vyamap-radius-sm);
    background: rgba(10, 16, 30, 0.9);
    color: var(--vyamap-text);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
    white-space: nowrap;
    backdrop-filter: blur(10px);
}

.visited-map :deep(.vyamap-visited-label span) {
    font-size: 0.8125rem;
    font-weight: 600;
    line-height: 1.25;
}

.visited-map :deep(.vyamap-visited-label small) {
    color: var(--vyamap-cyan-soft);
    font-size: 0.75rem;
    line-height: 1.25;
    font-variant-numeric: tabular-nums;
}

.visited-map :deep(.vyamap-visited-tooltip strong) {
    font-weight: 600;
}

.visited-map-overlay {
    position: absolute;
    z-index: 500;
    top: 1rem;
    left: 1rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--vyamap-border-strong);
    border-radius: var(--vyamap-radius-md);
    background: rgba(10, 16, 30, 0.7);
    backdrop-filter: blur(12px) saturate(140%);
    -webkit-backdrop-filter: blur(12px) saturate(140%);
    pointer-events: none;
}

.visited-map-kicker {
    color: var(--vyamap-cyan-soft);
    font-size: 0.75rem;
    font-weight: 600;
}

.visited-map-count {
    color: var(--vyamap-text);
    font-size: 0.875rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

/* Viaje planificado (violeta), en curso (violeta con pulso) o cancelado (gris). */
.visited-map--planned :deep(.vyamap-visited-pin) {
    border-color: rgba(167, 139, 250, 0.55);
    box-shadow:
        0 0 0 4px rgba(167, 139, 250, 0.1),
        0 8px 20px rgba(0, 0, 0, 0.28);
}

.visited-map--planned :deep(.vyamap-visited-dot) {
    background: #A78BFA;
    box-shadow: 0 0 10px rgba(167, 139, 250, 0.6);
}

.visited-map--planned :deep(.vyamap-visited-label small),
.visited-map--planned .visited-map-kicker {
    color: rgba(196, 181, 253, 0.9);
}

.visited-map--ongoing :deep(.vyamap-visited-dot) {
    animation: visited-map-pulse 1.8s ease-in-out infinite;
}

@keyframes visited-map-pulse {
    50% {
        box-shadow: 0 0 0 6px rgba(167, 139, 250, 0.22);
    }
}

@media (prefers-reduced-motion: reduce) {
    .visited-map--ongoing :deep(.vyamap-visited-dot) {
        animation: none;
    }
}

.visited-map--cancelled :deep(.vyamap-visited-pin) {
    border-color: rgba(255, 255, 255, 0.18);
    box-shadow: none;
}

.visited-map--cancelled :deep(.vyamap-visited-dot) {
    background: rgba(255, 255, 255, 0.35);
    box-shadow: none;
}

.visited-map--cancelled .visited-map-kicker {
    color: rgba(255, 255, 255, 0.4);
}
</style>
