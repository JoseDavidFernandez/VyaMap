<script setup lang="ts">
/**
 * Iconos de VyaMap: SVG propios, rejilla de 24 y trazo único de 1.75.
 * Son decorativos (aria-hidden): el texto o el aria-label del control que los
 * contiene es el que nombra la acción.
 */
export type IconName =
    | 'home' | 'trips' | 'flight' | 'passport' | 'globe' | 'photo' | 'user'
    | 'plus' | 'logout' | 'menu' | 'close' | 'arrow-right' | 'arrow-left'
    | 'chevron-left' | 'chevron-right' | 'chevron-down' | 'pin' | 'calendar'
    | 'edit' | 'trash' | 'search' | 'route' | 'clock' | 'check' | 'external'
    | 'map' | 'image-plus' | 'compass' | 'mail' | 'lock' | 'more' | 'swap';

withDefaults(defineProps<{
    name: IconName;
    size?: number | string;
}>(), {
    size: 20,
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :width="size"
        :height="size"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <template v-if="name === 'home'">
            <path d="M4 10.5 12 4l8 6.5" />
            <path d="M6 9v10h12V9" />
            <path d="M10 19v-5h4v5" />
        </template>
        <template v-else-if="name === 'trips'">
            <rect x="4" y="7" width="16" height="12" rx="2.5" />
            <path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7" />
            <path d="M4 12.5h16" />
        </template>
        <template v-else-if="name === 'flight'">
            <path d="M10.5 13.5 4 11l1.5-1.5 7 .5 3.8-3.8a1.9 1.9 0 0 1 2.7 2.7L15.2 12.7l.5 7L14.2 21l-2.5-6.5" />
            <path d="m6 17 2-2" />
        </template>
        <template v-else-if="name === 'passport'">
            <rect x="5" y="3.5" width="14" height="17" rx="2.5" />
            <circle cx="12" cy="10.5" r="3" />
            <path d="M9 10.5h6" />
            <path d="M9.5 16.5h5" />
        </template>
        <template v-else-if="name === 'globe'">
            <circle cx="12" cy="12" r="8.5" />
            <path d="M3.5 12h17" />
            <path d="M12 3.5c2.4 2.5 3.5 5.3 3.5 8.5s-1.1 6-3.5 8.5c-2.4-2.5-3.5-5.3-3.5-8.5S9.6 6 12 3.5Z" />
        </template>
        <template v-else-if="name === 'photo'">
            <rect x="3.5" y="5" width="17" height="14" rx="2.5" />
            <circle cx="9" cy="10" r="1.75" />
            <path d="m20.5 15.5-4.5-4.5-8.5 8" />
        </template>
        <template v-else-if="name === 'image-plus'">
            <path d="M20.5 12V16.5a2.5 2.5 0 0 1-2.5 2.5H6a2.5 2.5 0 0 1-2.5-2.5V7.5A2.5 2.5 0 0 1 6 5h7" />
            <circle cx="9" cy="10" r="1.75" />
            <path d="m20 16-4-4-8.5 7" />
            <path d="M18 3v6M15 6h6" />
        </template>
        <template v-else-if="name === 'user'">
            <circle cx="12" cy="8.5" r="3.75" />
            <path d="M4.5 20c1.2-3.6 4.1-5.5 7.5-5.5s6.3 1.9 7.5 5.5" />
        </template>
        <template v-else-if="name === 'plus'">
            <path d="M12 5v14M5 12h14" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M14 4h3.5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5H14" />
            <path d="M10 8 6 12l4 4" />
            <path d="M6 12h10" />
        </template>
        <template v-else-if="name === 'menu'">
            <path d="M4 7h16M4 12h16M4 17h16" />
        </template>
        <template v-else-if="name === 'more'">
            <circle cx="6" cy="12" r="1.25" />
            <circle cx="12" cy="12" r="1.25" />
            <circle cx="18" cy="12" r="1.25" />
        </template>
        <template v-else-if="name === 'close'">
            <path d="M6 6l12 12M18 6 6 18" />
        </template>
        <template v-else-if="name === 'arrow-right'">
            <path d="M5 12h14M13 6l6 6-6 6" />
        </template>
        <template v-else-if="name === 'arrow-left'">
            <path d="M19 12H5M11 6l-6 6 6 6" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <path d="m15 6-6 6 6 6" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <path d="m9 6 6 6-6 6" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="m6 9 6 6 6-6" />
        </template>
        <template v-else-if="name === 'pin'">
            <path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z" />
            <circle cx="12" cy="10" r="2.25" />
        </template>
        <template v-else-if="name === 'calendar'">
            <rect x="4" y="5.5" width="16" height="14.5" rx="2.5" />
            <path d="M8 3.5v4M16 3.5v4M4 10.5h16" />
        </template>
        <template v-else-if="name === 'edit'">
            <path d="M15.5 5.5 18.5 8.5" />
            <path d="M5 19l1-4L16.2 4.8a2.1 2.1 0 0 1 3 3L9 18l-4 1Z" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M5 7h14" />
            <path d="M10 4h4" />
            <path d="M7 7l.8 11.2A2 2 0 0 0 9.8 20h4.4a2 2 0 0 0 2-1.8L17 7" />
            <path d="M10.5 11v5M13.5 11v5" />
        </template>
        <template v-else-if="name === 'search'">
            <circle cx="11" cy="11" r="6.5" />
            <path d="m20 20-4.2-4.2" />
        </template>
        <template v-else-if="name === 'route'">
            <circle cx="6" cy="18" r="2.25" />
            <circle cx="18" cy="6" r="2.25" />
            <path d="M8.25 18H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.75" />
        </template>
        <template v-else-if="name === 'clock'">
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7.5V12l3 2" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="m5 12.5 4.5 4.5L19 7.5" />
        </template>
        <template v-else-if="name === 'external'">
            <path d="M14 4h6v6" />
            <path d="M20 4 11 13" />
            <path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" />
        </template>
        <template v-else-if="name === 'map'">
            <path d="M9 5 3.5 7v12L9 17l6 2 5.5-2V5L15 7 9 5Z" />
            <path d="M9 5v12M15 7v12" />
        </template>
        <template v-else-if="name === 'compass'">
            <circle cx="12" cy="12" r="8.5" />
            <path d="m15.5 8.5-2 5-5 2 2-5 5-2Z" />
        </template>
        <template v-else-if="name === 'mail'">
            <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" />
            <path d="m4.5 7 7.5 6 7.5-6" />
        </template>
        <template v-else-if="name === 'swap'">
            <path d="M7 4 4 7l3 3" />
            <path d="M4 7h16" />
            <path d="m17 14 3 3-3 3" />
            <path d="M20 17H4" />
        </template>
        <template v-else-if="name === 'lock'">
            <rect x="5" y="10.5" width="14" height="10" rx="2.5" />
            <path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" />
        </template>
    </svg>
</template>
