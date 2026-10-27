<script setup lang="ts">
import Icon, { type IconName } from './Icon.vue';

/**
 * Estado vacío diseñado: icono, qué falta, por qué importa y la acción que lo
 * resuelve (slot «actions»). `compact` para huecos dentro de un panel.
 */
withDefaults(defineProps<{
    icon?: IconName;
    title: string;
    text?: string;
    compact?: boolean;
}>(), {
    icon: 'compass',
    text: undefined,
    compact: false,
});
</script>

<template>
    <div
        class="vyamap-empty"
        :class="compact ? '!gap-2 !py-6' : ''"
    >
        <span class="vyamap-empty-icon" :class="compact ? '!h-10 !w-10' : ''">
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
