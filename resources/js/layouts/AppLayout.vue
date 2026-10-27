<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Navbar from '../components/Navbar.vue';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen text-[var(--vyamap-text)]">
        <Head v-if="title" :title="title" />

        <a
            href="#main"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-[var(--vyamap-radius-sm)] focus:bg-[var(--vyamap-cyan)] focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#041019]"
        >
            Skip to content
        </a>

        <Navbar />

        <main
            id="main"
            class="min-h-screen pb-[calc(5rem+env(safe-area-inset-bottom))] pt-[calc(3.5rem+env(safe-area-inset-top))] lg:pb-0 lg:pl-[72px] lg:pt-0 xl:pl-[var(--vyamap-nav-width)]"
        >
            <slot />
        </main>
    </div>
</template>
