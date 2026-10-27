<script setup lang="ts">
import { stockCredit, stockSrc, stockSrcSet, type StockImage } from '../../data/stockImages';

/**
 * Imagen genérica de paisaje (data/stockImages.ts) con su etiqueta visible
 * «Illustrative image», para que nunca pase por una foto real del usuario.
 * Ocupa todo su contenedor; el contenedor fija el tamaño.
 */
withDefaults(defineProps<{
    image: StockImage;
    sizes?: string;
    label?: boolean;
    eager?: boolean;
}>(), {
    sizes: '(min-width: 1024px) 33vw, 100vw',
    label: true,
    eager: false,
});
</script>

<template>
    <span class="vyamap-stock">
        <img
            :src="stockSrc(image)"
            :srcset="stockSrcSet(image)"
            :sizes="sizes"
            :alt="image.alt"
            :loading="eager ? 'eager' : 'lazy'"
            decoding="async"
            class="h-full w-full object-cover"
        />
        <span v-if="label" class="vyamap-stock-label" :title="stockCredit(image)">
            Illustrative image
        </span>
    </span>
</template>

<style>
.vyamap-stock {
    position: relative;
    display: block;
    height: 100%;
    width: 100%;
    overflow: hidden;
    background: var(--vyamap-surface-strong);
}

/* Velo de la paleta: integra la foto en el fondo nocturno y la distingue de las fotos propias */
.vyamap-stock::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        linear-gradient(180deg, rgba(7, 11, 20, 0) 40%, rgba(7, 11, 20, 0.55) 100%),
        linear-gradient(135deg, rgba(34, 211, 238, 0.10), rgba(124, 92, 255, 0.14));
}

.vyamap-stock img {
    filter: saturate(0.85);
}

.vyamap-stock-label {
    position: absolute;
    right: 0.5rem;
    bottom: 0.5rem;
    z-index: 1;
    border-radius: 9999px;
    background: rgba(7, 11, 20, 0.72);
    padding: 0.125rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: var(--vyamap-text-muted);
    cursor: help;
}
</style>
