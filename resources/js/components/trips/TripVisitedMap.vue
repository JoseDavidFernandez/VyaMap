<script setup lang="ts">

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';

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
    // Fase del viaje: lima para planificados, en curso o pendientes; gris si está cancelado.
    phase?: 'upcoming' | 'ongoing' | 'pending' | 'completed' | 'cancelled';
}>(), {
    phase: 'completed',
});

const overlayKicker = computed(() => ({
    completed: 'PLACES VISITED',
    upcoming: 'PLANNED DESTINATIONS',
    ongoing: 'TRIP IN PROGRESS',
    pending: 'PENDING CONFIRMATION',
    cancelled: 'CANCELLED TRIP',
})[props.phase]);

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let markerLayer: L.LayerGroup | null = null;

const escapeHtml = (value: string | null | undefined) => {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

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
        class="visited-map"
        :class="{
            'visited-map--planned': phase === 'upcoming' || phase === 'ongoing' || phase === 'pending',
            'visited-map--ongoing': phase === 'ongoing',
            'visited-map--cancelled': phase === 'cancelled',
        }"
    >
        <div class="visited-map-overlay">
            <div class="visited-map-kicker">{{ overlayKicker }}</div>
            <div class="visited-map-count">{{ visits.length }} cities</div>
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
    background: #10191c;
}

.visited-map :deep(.leaflet-tile-pane) {
    /* Base oscura como en los mapas de vuelos, con algo de color en agua y tierra. */
    filter: invert(1) hue-rotate(180deg) brightness(0.68) contrast(0.92) saturate(0.6);
}

.visited-map :deep(.leaflet-control-zoom) {
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 10px !important;
    background: rgba(16, 19, 22, 0.88) !important;
    box-shadow: none !important;
}

.visited-map :deep(.leaflet-control-zoom a) {
    width: 30px;
    height: 30px;
    border: 0 !important;
    background: transparent !important;
    color: rgba(255, 255, 255, 0.72) !important;
    line-height: 30px;
}

.visited-map :deep(.leaflet-control-zoom a:hover) {
    background: rgba(103, 232, 249, 0.1) !important;
    color: #a5f3fc !important;
}

.visited-map :deep(.leaflet-control-attribution) {
    padding: 2px 6px;
    border-radius: 4px 0 0 0;
    background: rgba(10, 12, 14, 0.68);
    color: rgba(255, 255, 255, 0.36);
    font-size: 8px;
}

.visited-map :deep(.leaflet-control-attribution a) {
    color: rgba(255, 255, 255, 0.5);
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
    background: rgba(15, 18, 21, 0.92);
    border: 1px solid rgba(103, 232, 249, 0.45);
    box-shadow:
        0 0 0 4px rgba(103, 232, 249, 0.08),
        0 8px 20px rgba(0, 0, 0, 0.28);
}

/* Punto de la ciudad: acento cian (cyan-300) de la paleta de VyaMap. */
.visited-map :deep(.vyamap-visited-dot) {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #67e8f9;
    box-shadow: 0 0 10px rgba(103, 232, 249, 0.65);
}

.visited-map :deep(.vyamap-visited-label) {
    display: flex;
    flex-direction: column;
    gap: 1px;
    padding: 6px 9px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 9px;
    background: rgba(15, 18, 21, 0.9);
    color: #ffffff;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
    white-space: nowrap;
    backdrop-filter: blur(10px);
}

.visited-map :deep(.vyamap-visited-label span) {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
}

.visited-map :deep(.vyamap-visited-label small) {
    color: rgba(165, 243, 252, 0.6);
    font-size: 8px;
    line-height: 1.2;
}

.visited-map :deep(.vyamap-visited-tooltip) {
    border: 1px solid rgba(103, 232, 249, 0.18);
    border-radius: 9px;
    background: rgba(15, 18, 21, 0.96);
    color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
    font-size: 10px;
    line-height: 1.5;
}

.visited-map :deep(.vyamap-visited-tooltip strong) {
    color: #ffffff;
    font-weight: 600;
}

.visited-map :deep(.leaflet-tooltip-top:before) {
    border-top-color: rgba(15, 18, 21, 0.96);
}

.visited-map-overlay {
    position: absolute;
    z-index: 500;
    top: 18px;
    left: 18px;
    padding: 9px 11px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    background: rgba(15, 18, 21, 0.82);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
    backdrop-filter: blur(12px);
    pointer-events: none;
}

.visited-map-kicker {
    color: rgba(103, 232, 249, 0.7);
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 0.16em;
}

.visited-map-count {
    margin-top: 2px;
    color: rgba(255, 255, 255, 0.85);
    font-size: 11px;
}

/* Viaje planificado (lima), en curso (lima con pulso) o cancelado (gris). */
.visited-map--planned :deep(.vyamap-visited-pin) {
    border-color: rgba(190, 242, 100, 0.5);
    box-shadow:
        0 0 0 4px rgba(190, 242, 100, 0.08),
        0 8px 20px rgba(0, 0, 0, 0.28);
}

.visited-map--planned :deep(.vyamap-visited-dot) {
    background: #bef264;
    box-shadow: 0 0 10px rgba(190, 242, 100, 0.6);
}

.visited-map--planned :deep(.vyamap-visited-label small),
.visited-map--planned .visited-map-kicker {
    color: rgba(217, 249, 157, 0.75);
}

.visited-map--ongoing :deep(.vyamap-visited-dot) {
    animation: visited-map-pulse 1.8s ease-in-out infinite;
}

@keyframes visited-map-pulse {
    50% {
        box-shadow: 0 0 0 6px rgba(190, 242, 100, 0.2);
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
