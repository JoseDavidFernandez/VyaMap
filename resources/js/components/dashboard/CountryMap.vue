<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';

interface Country {
    id: number;
    name: string;
    iso_code: string;
}

const props = defineProps<{
    countries: Country[];
}>();

const mapElement = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let geoJsonLayer: L.GeoJSON | null = null;

onMounted(async () => {
    if (!mapElement.value) {
        return;
    }

    map = L.map(mapElement.value, {
        zoomControl: false,
        attributionControl: true,
        minZoom: 2,
        maxZoom: 5,
        worldCopyJump: true,
    });

    map.setView([20, 0], 2);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const visitedCountries = new Set(
        props.countries.map((country) => country.iso_code),
    );

    const response = await fetch('/data/world.json');

    if (!response.ok) {
        throw new Error('Unable to load world map data.');
    }

    const worldGeoJson = await response.json();

    geoJsonLayer = L.geoJSON(worldGeoJson, {
        style: (feature) => {
            const isoCode = feature?.properties?.ISO_A2;

            const visited = isoCode
                ? visitedCountries.has(isoCode)
                : false;

            return {
                fillColor: visited ? '#1D2235' : '#EBF3F8',
                fillOpacity: visited ? 0.9 : 0.7,
                color: '#C9D7DC',
                weight: 1,
            };
        },

        onEachFeature: (feature, layer) => {
            const name = feature.properties?.NAME;

            if (name) {
                layer.bindTooltip(name, {
                    sticky: true,
                });
            }
        },
    }).addTo(map);
});

onBeforeUnmount(() => {
    geoJsonLayer?.remove();
    map?.remove();

    geoJsonLayer = null;
    map = null;
});
</script>

<template>
    <div
        ref="mapElement"
        class="h-[520px] w-full rounded-[var(--vyamap-radius-xl)]"
    />
</template>