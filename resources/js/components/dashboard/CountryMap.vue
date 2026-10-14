<script setup lang="ts">
import L from 'leaflet';

import {
    computed,
    onMounted,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';
import { normalizeGeoJsonIsoCode } from '../../data/countryCodes';

interface Country {
    id: number;
    name: string;
    iso_code: string;
}

const props = withDefaults(defineProps<{
    countries: Country[];
    // 'light': estilo original (prototipos). 'dark': mapa personal de Home y Passport,
    // sin teselas, solo las siluetas de los países sobre la tarjeta.
    theme?: 'light' | 'dark';
}>(), {
    theme: 'light',
});

const PALETTES = {
    light: {
        visited: { fillColor: '#1D2235', fillOpacity: 0.9, color: '#C9D7DC', weight: 1 },
        other: { fillColor: '#EBF3F8', fillOpacity: 0.7, color: '#C9D7DC', weight: 1 },
    },
    // Acento cian, el mismo tono que los degradados de la Home (cyan-300).
    dark: {
        visited: { fillColor: '#67E8F9', fillOpacity: 0.55, color: 'rgba(103, 232, 249, 0.85)', weight: 0.8 },
        other: { fillColor: '#FFFFFF', fillOpacity: 0.05, color: 'rgba(255, 255, 255, 0.14)', weight: 0.5 },
    },
} satisfies Record<'light' | 'dark', Record<'visited' | 'other', L.PathOptions>>;

// Al pasar el cursor, en el tema oscuro, el país se ilumina ligeramente.
const DARK_HOVER = {
    visited: { fillOpacity: 0.75, color: '#A5F3FC', weight: 1 },
    other: { fillOpacity: 0.1, color: 'rgba(255, 255, 255, 0.3)', weight: 0.8 },
} satisfies Record<'visited' | 'other', L.PathOptions>;

// Encuadre del tema oscuro: el mundo habitado, sin la Antártida.
const DARK_WORLD_BOUNDS = L.latLngBounds([-58, -170], [78, 180]);

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let geoJsonLayer: L.GeoJSON | null = null;

const visitedCountries = computed(() => new Set(
    props.countries.map(
        (country) => country.iso_code,
    ),
));

const isVisited = (feature?: GeoJSON.Feature) => {
    const isoCode = normalizeGeoJsonIsoCode(
        feature?.properties?.ISO_A2,
    );

    return isoCode
        ? visitedCountries.value.has(isoCode)
        : false;
};

const countryStyle = (feature?: GeoJSON.Feature): L.PathOptions =>
    PALETTES[props.theme][isVisited(feature) ? 'visited' : 'other'];

const fitDarkWorld = () => {
    map?.fitBounds(DARK_WORLD_BOUNDS, { padding: [8, 8] });
};

watch(
    () => props.countries,
    () => {
        if (!geoJsonLayer) {
            return;
        }

        geoJsonLayer.eachLayer((layer) => {
            const feature =
                (layer as L.Layer & {
                    feature?: GeoJSON.Feature;
                }).feature;

            if (layer instanceof L.Path) {
                layer.setStyle(countryStyle(feature));
            }
        });
    },
);

onMounted(async () => {
    if (!mapElement.value) {
        return;
    }

    const dark = props.theme === 'dark';

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,

        // El mapa del Passport es únicamente visual.
        // No permitimos zoom mediante rueda, pinch,
        // doble click ni botones.
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        dragging: false,
        touchZoom: false,

        // El tema oscuro se ajusta al tamaño de la tarjeta (zoom fraccionario);
        // el claro conserva su zoom fijo.
        ...(dark
            ? { zoomSnap: 0.1, minZoom: 0, maxZoom: 3 }
            : { minZoom: 2, maxZoom: 2 }),
        worldCopyJump: false,
    });

    if (dark) {
        fitDarkWorld();
        map.on('resize', fitDarkWorld);
    } else {
        map.setView([20, 0], 2);

        L.tileLayer(
            'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                attribution:
                    '&copy; OpenStreetMap contributors',
            },
        ).addTo(map);
    }

    const response = await fetch(
        '/data/world.json',
    );

    if (!response.ok) {
        throw new Error(
            'Unable to load world map data.',
        );
    }

    const worldGeoJson =
        await response.json();

    geoJsonLayer = L.geoJSON(
        worldGeoJson,
        {
            style: countryStyle,

            onEachFeature: (
                feature,
                layer,
            ) => {
                const name =
                    feature.properties?.NAME;

                if (name) {
                    layer.bindTooltip(
                        name,
                        {
                            sticky: true,
                            ...(dark ? { className: 'country-map-tooltip' } : {}),
                        },
                    );
                }

                if (dark && layer instanceof L.Path) {
                    layer.on('mouseover', () => {
                        layer.setStyle(DARK_HOVER[isVisited(feature) ? 'visited' : 'other']);
                    });

                    layer.on('mouseout', () => {
                        layer.setStyle(countryStyle(feature));
                    });
                }
            },
        },
    ).addTo(map);
});

onBeforeUnmount(() => {
    map?.off('resize', fitDarkWorld);
    geoJsonLayer?.remove();
    map?.remove();

    geoJsonLayer = null;
    map = null;
});
</script>

<template>
    <div
        ref="mapElement"
        :class="theme === 'dark'
            ? 'country-map-dark vyamap-leaflet-dark h-full w-full'
            : 'h-full min-h-[460px] w-full'"
    />
</template>

<style scoped>
/* Sin teselas: el fondo es el de la tarjeta que contiene el mapa. */
.country-map-dark {
    background: transparent;
}

.country-map-dark :deep(.country-map-tooltip) {
    padding: 4px 9px;
    border: 1px solid rgba(103, 232, 249, 0.18);
    border-radius: 8px;
    background: rgba(13, 17, 25, 0.95);
    color: rgba(255, 255, 255, 0.85);
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.35);
    font-size: 11px;
}

.country-map-dark :deep(.country-map-tooltip::before) {
    display: none;
}
</style>
