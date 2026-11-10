<script setup lang="ts">
/**
 * Logo de VyaMap: el mapa plegado con ruta y pin de public/favicon.svg, sin el
 * fondo. El tamaño se pone desde fuera con clases. Por defecto es decorativo
 * (va junto al texto «VyaMap»); con `standalone` se anuncia como imagen.
 */
withDefaults(defineProps<{ standalone?: boolean }>(), { standalone: false });
</script>

<template>
    <svg
        viewBox="8 12 48 42"
        xmlns="http://www.w3.org/2000/svg"
        :aria-hidden="standalone ? undefined : 'true'"
        :role="standalone ? 'img' : undefined"
        :aria-label="standalone ? 'VyaMap' : undefined"
    >
        <path d="M10 14 L28 19 L32 51 L17 44 Z" fill="#22d3ee" />
        <path d="M54 14 L36 19 L32 51 L47 44 Z" fill="#8b5cf6" />
        <path
            d="M18 36 Q28 22 42 30"
            stroke="#e2e8f0"
            stroke-width="2.5"
            fill="none"
            stroke-linecap="round"
            stroke-dasharray="1 4.5"
        />
        <circle cx="18" cy="36" r="2.5" fill="#e2e8f0" />
        <path d="M44 35 C44 35 38.5 29 38.5 25.5 A5.5 5.5 0 0 1 49.5 25.5 C49.5 29 44 35 44 35 Z" fill="#e2e8f0" />
        <circle cx="44" cy="25.5" r="2" fill="#8b5cf6" />
    </svg>
</template>
