<script setup lang="ts">
import Icon, { type IconName } from './Icon.vue';
import StockImage from './StockImage.vue';
import type { StockImage as StockImageData } from '../../data/stockImages';

/**
 * Estado vacío diseñado: icono, qué falta, por qué importa y la acción que lo
 * resuelve (slot «actions»). `compact` para huecos dentro de un panel; `image`
 * añade arriba un paisaje genérico, siempre etiquetado como ilustrativo.
 */
withDefaults(defineProps<{
    icon?: IconName;
    title: string;
    text?: string;
    compact?: boolean;
    image?: StockImageData;
}>(), {
    icon: 'compass',
    text: undefined,
    compact: false,
    image: undefined,
});
</script>

<template>
    <div
        class="vyamap-empty"
        :class="[compact ? '!gap-2 !py-6' : '', image && !compact ? '!pt-0 overflow-hidden' : '']"
    >
        <div
            v-if="image && !compact"
            class="-mx-6 mb-2 h-40 w-[calc(100%+3rem)] sm:h-52"
        >
            <StockImage :image="image" sizes="(min-width: 1024px) 70vw, 100vw" />
        </div>

        <span class="vyamap-empty-icon" :class="[compact ? '!h-10 !w-10' : '', image && !compact ? '-mt-9 relative z-[1] !bg-[var(--vyamap-surface)]' : '']">
            <Icon :name="icon" :size="compact ? 18 : 22" />
        </span>

        <p class="vyamap-empty-title" :class="compact ? '!text-[0.9375rem]' : ''">
            {{ title }}
        </p>

        <p v-if="text" class="vyamap-empty-text">
            {{ text }}
        </p>

        <div v-if="$slots.actions" class="mt-1 flex flex-wrap items-center justify-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
